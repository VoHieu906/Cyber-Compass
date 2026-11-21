<?php

/*
| Vietnamese validation messages for the rules CyberCompass uses. Rules not listed
| here fall back to English (APP_FALLBACK_LOCALE). Add a line when a new rule is used.
*/

return [
    'after' => ':attribute phải sau ngày :date.',
    'before' => ':attribute phải trước ngày :date.',
    'boolean' => ':attribute phải là đúng hoặc sai.',
    'confirmed' => ':attribute xác nhận không khớp.',
    'current_password' => 'Mật khẩu hiện tại không đúng.',
    'date' => ':attribute không phải là ngày hợp lệ.',
    'date_format' => ':attribute phải có định dạng :format.',
    'different' => ':attribute phải khác :other.',
    'dimensions' => ':attribute có kích thước ảnh không hợp lệ.',
    'email' => ':attribute phải là địa chỉ email hợp lệ.',
    'enum' => ':attribute đã chọn không hợp lệ.',
    'file' => ':attribute phải là một tệp.',
    'image' => ':attribute phải là hình ảnh.',
    'in' => ':attribute đã chọn không hợp lệ.',
    'max_bytes' => ':attribute quá dài. Hãy dùng ít ký tự hơn (tối đa :max byte).',
    'max' => [
        'file' => ':attribute không được lớn hơn :max KB.',
        'string' => ':attribute không được dài quá :max ký tự.',
    ],
    'mimes' => ':attribute phải là tệp thuộc loại: :values.',
    'min' => [
        'string' => ':attribute phải có ít nhất :min ký tự.',
    ],
    'regex' => ':attribute có định dạng không hợp lệ.',
    'required' => 'Vui lòng nhập :attribute.',
    'string' => ':attribute phải là chuỗi ký tự.',
    'unique' => ':attribute này đã được sử dụng.',
    'uploaded' => 'Không thể tải lên :attribute.',
    'password' => [
        'letters' => ':attribute phải chứa ít nhất một chữ cái.',
        'mixed' => ':attribute phải chứa cả chữ hoa và chữ thường.',
        'numbers' => ':attribute phải chứa ít nhất một chữ số.',
        'symbols' => ':attribute phải chứa ít nhất một ký tự đặc biệt.',
        'uncompromised' => ':attribute này đã xuất hiện trong một vụ rò rỉ dữ liệu. Vui lòng chọn mật khẩu khác.',
    ],

    'attributes' => [
        'name' => 'họ và tên',
        'email' => 'email',
        'password' => 'mật khẩu',
        'current_password' => 'mật khẩu hiện tại',
        'phone' => 'số điện thoại',
        'date_of_birth' => 'ngày sinh',
        'education_level' => 'trình độ học vấn',
        'avatar' => 'ảnh đại diện',
        'locale' => 'ngôn ngữ',
        'theme' => 'giao diện',
        'token' => 'mã đặt lại',
    ],
];
