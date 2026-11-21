<?php

// Email text for learner notifications (D34). The in-app text lives in the frontend messages.
return [
    'greeting' => 'Hi :name,',
    'footer' => 'You can choose which emails you get under Settings, Notifications.',
    'recommendation' => [
        'subject' => 'New lesson for you: :lesson',
        'line' => 'A new lesson, ":lesson", was published in :context.',
        'action' => 'Open the lesson',
    ],
    'lesson_completed' => [
        'subject' => 'Lesson completed: :lesson',
        'line' => 'You completed ":lesson". Your progress is saved on your dashboard.',
        'action' => 'Open the dashboard',
    ],
    'assessment_result' => [
        'subject' => 'Your assessment result: :score%',
        'line' => 'You scored :score% on the skills assessment. Your result shows each domain and what to study next.',
        'action' => 'See the result',
    ],
    'reminder' => [
        'subject' => 'A few minutes of study today?',
        'line' => 'You haven\'t studied yet today. Even one short lesson keeps your learning moving.',
        'action' => 'Continue learning',
    ],
    'milestone' => [
        'course' => [
            'subject' => 'Course completed: :title',
            'line' => 'You finished every lesson in ":title". Well done.',
            'action' => 'Open the course',
        ],
        'roadmap' => [
            'subject' => 'Your roadmap is :percent% done',
            'line' => 'You have completed :percent% of your :title roadmap.',
            'action' => 'Open your roadmap',
        ],
        'streak' => [
            'subject' => ':days days in a row',
            'line' => 'You have studied :days days in a row. Keep it going.',
            'action' => 'Open the dashboard',
        ],
    ],
];
