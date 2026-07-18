<?php

return [
    'accepted' => '必须接受 :attribute。',
    'array' => ':attribute 必须是数组。',
    'boolean' => ':attribute 必须为 true 或 false。',
    'confirmed' => ':attribute 确认不匹配。',
    'email' => ':attribute 必须是有效的邮箱地址。',
    'exists' => '所选 :attribute 无效。',
    'integer' => ':attribute 必须是整数。',
    'max' => ['string' => ':attribute 不能超过 :max 个字符', 'array' => ':attribute 不能超过 :max 项', 'numeric' => ':attribute 不能大于 :max'],
    'min' => ['string' => ':attribute 至少需要 :min 个字符', 'numeric' => ':attribute 必须至少为 :min', 'array' => ':attribute 至少需要 :min 项'],
    'nullable' => ':attribute 可以为空。',
    'required' => ':attribute 不能为空。',
    'string' => ':attribute 必须是字符串。',
    'url' => ':attribute 必须是有效的网址。',
    'unique' => ':attribute 已经被使用。',
    'in' => '所选 :attribute 无效。',
    'attributes' => [
        'name' => '昵称', 'email' => '邮箱', 'password' => '密码', 'password_confirmation' => '确认密码', 'role' => '注册身份', 'title' => '作品名称', 'summary' => '作品简介', 'content' => '正文', 'manuscript' => '稿件', 'review_note' => '审核意见', 'code' => '验证码', 'current_password' => '当前密码', 'site_name' => '站点名称', 'site_tagline' => '站点副标题', 'contact_email' => '联系邮箱', 'rating' => '评分', 'genre' => '作品分类',
    ],
];
