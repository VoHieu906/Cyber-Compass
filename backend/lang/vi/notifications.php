<?php

// Nội dung email cho thông báo của người học (D34). Nội dung trong ứng dụng nằm ở frontend.
return [
    'greeting' => 'Chào :name,',
    'footer' => 'Bạn có thể chọn loại email muốn nhận trong Cài đặt, Thông báo.',
    'recommendation' => [
        'subject' => 'Bài học mới cho bạn: :lesson',
        'line' => 'Bài học mới ":lesson" vừa được đăng trong :context.',
        'action' => 'Mở bài học',
    ],
    'lesson_completed' => [
        'subject' => 'Đã hoàn thành bài: :lesson',
        'line' => 'Bạn đã hoàn thành ":lesson". Tiến độ của bạn được lưu trên bảng điều khiển.',
        'action' => 'Mở bảng điều khiển',
    ],
    'assessment_result' => [
        'subject' => 'Kết quả đánh giá của bạn: :score%',
        'line' => 'Bạn đạt :score% trong bài đánh giá kỹ năng. Kết quả cho thấy từng lĩnh vực và nên học gì tiếp theo.',
        'action' => 'Xem kết quả',
    ],
    'reminder' => [
        'subject' => 'Học vài phút hôm nay nhé?',
        'line' => 'Hôm nay bạn chưa học. Chỉ một bài ngắn cũng giúp việc học tiếp tục.',
        'action' => 'Học tiếp',
    ],
    'milestone' => [
        'course' => [
            'subject' => 'Đã hoàn thành khóa học: :title',
            'line' => 'Bạn đã học xong mọi bài trong ":title". Làm tốt lắm.',
            'action' => 'Mở khóa học',
        ],
        'roadmap' => [
            'subject' => 'Lộ trình của bạn đã xong :percent%',
            'line' => 'Bạn đã hoàn thành :percent% lộ trình :title.',
            'action' => 'Mở lộ trình',
        ],
        'streak' => [
            'subject' => ':days ngày liên tiếp',
            'line' => 'Bạn đã học :days ngày liên tiếp. Hãy giữ chuỗi nhé.',
            'action' => 'Mở bảng điều khiển',
        ],
    ],
];
