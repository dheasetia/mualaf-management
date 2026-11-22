<?php

declare(strict_types=1);

namespace App\Domain\Registration\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Converts\Enums\DisclosureLevel;
use App\Domain\Converts\Models\Convert;
use App\Domain\Converts\Models\ConvertContact;
use App\Domain\Converts\Services\IdentityRegistry;
use App\Domain\Registration\Data\ShahadahRegistrationData;
use App\Domain\Registration\Enums\CandidateType;
use App\Domain\Registration\Enums\MatchReason;
use App\Domain\Registration\Enums\ReviewDecision;
use App\Domain\Registration\Enums\ReviewStatus;
use App\Domain\Registration\Exceptions\RegistrationRuleViolation;
use App\Domain\Registration\Models\DuplicateReviewCase;
use App\Models\User;
use App\Support\Identity\PhoneNumber;
use App\Support\Tenancy\TenantContext;
use App\Support\Text\NameSearchKey;
use App\Support\Text\NameSimilarity;
use Illuminate\Support\Collection;

/**
 * Fuzzy duplicate check before a registration (04-identity-privacy §4.4 step 2):
 * converts in any office whose name, date of birth, nationality and gender
 * are close enough, or who share the phone number, hold the registration
 * until duplicates.review decides. "Different person" clears that pair for
 * good; "same person" keeps the registration blocked (transfer instead).
 *
 * High-confidentiality converts are left out of this name-based search
 * (04 §4.6); an exact identity still blocks them.
 */
final readonly class DuplicateScreen
{
    private const float WEIGHT_NAME = 0.6;

    private const float WEIGHT_DOB = 0.25;

    private const float WEIGHT_NATIONALITY = 0.1;

    private const float WEIGHT_GENDER = 0.05;

    /** Below this name score, matching other fields is coincidence. */
    private const float MIN_NAME_SCORE = 0.75;

    private const int MAX_CASES = 3;

    public function __construct(
        private IdentityRegistry $identities,
        private TenantContext $tenantContext,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  string|null  $phone  the contact the registration will store
     */
    public function screen(ShahadahRegistrationData $data, ?string $phone, User $actor): void
    {
        $candidateKey = hash('sha256', $this->identities->fingerprint($data->identity)['fingerprint']);
        $matches = $this->matches($data, $phone);

        if ($matches === []) {
            return;
        }

        $blocking = false;
        $opened = 0;

        foreach ($matches as $match) {
            $previous = DuplicateReviewCase::query()
                ->where('candidate_key', $candidateKey)
                ->where('possible_convert_id', $match['convert']->id)
                ->latest('id')
                ->first();

            if ($previous?->decision === ReviewDecision::DifferentPerson && $previous->status === ReviewStatus::Resolved) {
                continue;
            }

            $blocking = true;

            if ($previous !== null) {
                continue;
            }

            $case = new DuplicateReviewCase;
            $case->candidate_type = $data->prospectId === null ? CandidateType::RegistrationDraft : CandidateType::Prospect;
            $case->candidate_id = $data->prospectId;
            $case->candidate_key = $candidateKey;
            $case->possible_convert_id = $match['convert']->id;
            $case->match_reason = $match['reason'];
            $case->match_score = number_format($match['score'], 3, '.', '');
            $case->match_details = $match['details'];
            $case->status = ReviewStatus::Open;
            $case->created_by = $actor->id;
            $case->save();
            $opened++;

            $this->audit->record('duplicates.case_opened', newValues: [
                'case_id' => $case->id,
                'match_reason' => $match['reason']->value,
                'match_score' => $case->match_score,
            ], actor: $actor);
        }

        if ($blocking) {
            throw RegistrationRuleViolation::possibleDuplicate();
        }
    }

    /**
     * @return list<array{convert: Convert, reason: MatchReason, score: float, details: array<string, mixed>}>
     */
    private function matches(ShahadahRegistrationData $data, ?string $phone): array
    {
        $threshold = (float) config('identity.duplicate_threshold');
        $profile = $data->profile;
        $nameKey = NameSearchKey::make($profile->fullName);
        $prefix = mb_substr(explode(' ', $nameKey)[0], 0, 3);

        return $this->tenantContext->bypass('duplicate screening', function () use ($phone, $threshold, $profile, $nameKey, $prefix): array {
            $matches = [];

            foreach ($this->phoneMatches($phone) as $convert) {
                $matches[$convert->id] = [
                    'convert' => $convert,
                    'reason' => MatchReason::PhoneMatch,
                    'score' => 1.0,
                    'details' => ['candidate_name' => $profile->fullName, 'phone_match' => true],
                ];
            }

            $candidates = Convert::query()
                ->where('disclosure_risk_level', '!=', DisclosureLevel::HighConfidentiality)
                ->where(fn ($query) => $query
                    ->when($profile->dateOfBirth !== null, fn ($query) => $query->orWhereDate('date_of_birth', $profile->dateOfBirth))
                    ->orWhere(fn ($query) => $query
                        ->where('nationality_id', $profile->nationalityId)
                        ->where('name_search_key', 'like', addcslashes($prefix, '%_\\').'%')))
                ->limit(200)
                ->get();

            foreach ($candidates as $convert) {
                if (isset($matches[$convert->id])) {
                    continue;
                }

                $nameScore = max(array_map(
                    static fn (?string $name): float => $name === null ? 0.0 : NameSimilarity::score($nameKey, $name),
                    [$convert->full_name, $convert->name_ar, $convert->previous_name],
                ));

                if ($nameScore < self::MIN_NAME_SCORE) {
                    continue;
                }

                $weighted = self::WEIGHT_NAME * $nameScore;
                $weights = self::WEIGHT_NAME;
                $dobMatch = null;

                if ($profile->dateOfBirth !== null && $convert->date_of_birth !== null) {
                    $dobMatch = $profile->dateOfBirth->isSameDay($convert->date_of_birth);
                    $weighted += self::WEIGHT_DOB * ($dobMatch ? 1 : 0);
                    $weights += self::WEIGHT_DOB;
                }

                $nationalityMatch = $profile->nationalityId === $convert->nationality_id;
                $genderMatch = $profile->gender === $convert->gender;
                $weighted += self::WEIGHT_NATIONALITY * ($nationalityMatch ? 1 : 0) + self::WEIGHT_GENDER * ($genderMatch ? 1 : 0);
                $weights += self::WEIGHT_NATIONALITY + self::WEIGHT_GENDER;
                $score = $weighted / $weights;

                if ($score < $threshold) {
                    continue;
                }

                $matches[$convert->id] = [
                    'convert' => $convert,
                    'reason' => MatchReason::FuzzyNameDob,
                    'score' => $score,
                    'details' => [
                        'candidate_name' => $profile->fullName,
                        'candidate_date_of_birth' => $profile->dateOfBirth?->toDateString(),
                        'name_score' => round($nameScore, 3),
                        'date_of_birth_match' => $dobMatch,
                        'nationality_match' => $nationalityMatch,
                        'gender_match' => $genderMatch,
                    ],
                ];
            }

            usort($matches, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

            return array_slice($matches, 0, self::MAX_CASES);
        });
    }

    /** @return Collection<int, Convert> */
    private function phoneMatches(?string $phone): Collection
    {
        $fingerprint = PhoneNumber::fingerprint($phone);

        if ($fingerprint === null) {
            return collect();
        }

        $convertIds = ConvertContact::query()->where('value_fingerprint', $fingerprint)->where('active', true)->pluck('convert_id');

        return Convert::query()
            ->whereKey($convertIds)
            ->where('disclosure_risk_level', '!=', DisclosureLevel::HighConfidentiality)
            ->get();
    }
}
