<?php

declare(strict_types=1);

return [
    'model' => 'exam',
    'plural' => 'Exams',
    'attempts' => 'Candidates',
    'fields' => [
        'title' => 'Title', 'program' => 'Program', 'class' => 'Class', 'exam_type' => 'Type', 'maximum_score' => 'Maximum score',
        'passing_score' => 'Passing score', 'scheduled_at' => 'Scheduled', 'status' => 'Status', 'components' => 'Components',
        'component_code' => 'Code', 'name' => 'Name', 'max_score' => 'Maximum', 'weight' => 'Weight', 'enrollments' => 'Candidates',
        'convert' => 'Convert', 'attempt_number' => 'Attempt', 'final_score' => 'Final score', 'passed' => 'Passed',
        'result_status' => 'Result', 'reason' => 'Reason', 'score' => 'Score',
    ],
    'actions' => [
        'start' => 'Start', 'cancel' => 'Cancel exam', 'submit' => 'Submit for finalization', 'reopen' => 'Send back',
        'finalize' => 'Finalize', 'add_attempts' => 'Add candidates', 'grade' => 'Grade', 'absent' => 'Absent', 'amend' => 'Amend result',
    ],
    'notifications' => ['saved' => 'Saved.'],
    'types' => ['evaluation' => 'Evaluation', 'practical' => 'Practical', 'monthly' => 'Monthly', 'final' => 'Final', 'remedial' => 'Remedial'],
    'statuses' => ['scheduled' => 'Scheduled', 'in_progress' => 'In progress', 'graded' => 'Graded', 'pending_finalization' => 'Waiting for finalization', 'finalized' => 'Finalized', 'cancelled' => 'Cancelled'],
    'attempt_statuses' => ['scheduled' => 'Scheduled', 'absent' => 'Absent', 'in_progress' => 'In progress', 'graded' => 'Graded', 'finalized' => 'Finalized'],
    'result_statuses' => ['provisional' => 'Provisional', 'finalized' => 'Final', 'amended' => 'Amended'],
    'errors' => [
        'program_unavailable' => 'Choose a program available to this center.',
        'class_not_in_program' => 'The class must belong to the exam program.',
        'components_required' => 'Add at least one component.',
        'passing_above_maximum' => 'The passing score cannot exceed the maximum score.',
        'not_editable' => 'The exam can no longer be changed at this stage.',
        'invalid_transition' => 'The exam cannot move from :from to :to.',
        'enrollment_not_eligible' => 'Candidates must be enrolled in the exam program (and class).',
        'attempt_open' => 'This candidate already has an open attempt.',
        'already_passed' => 'This candidate already passed this exam.',
        'not_your_class' => 'Only teachers of the candidate\'s class can grade this attempt.',
        'scores_incomplete' => 'Enter a score for every component.',
        'score_out_of_range' => 'The score for :component is out of range.',
        'not_graded' => 'Grade every attempt first.',
        'reason_required' => 'A reason is required.',
        'not_finalized' => 'Only finalized results are amended; change provisional scores by grading again.',
    ],
];
