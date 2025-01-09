<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
// 排除webman框架文件
$excludeFiles = [

];
$finder = Finder::create()
    ->in([
        __DIR__ . '/src',
    ])->filter(function (\SplFileInfo $file) use ($excludeFiles) {
        return !in_array($file->getPath() . DIRECTORY_SEPARATOR . $file->getFilename(), $excludeFiles);
    })
    ->name('*.php');

$config = new Config();
return $config->setRules([
    // 应用 PSR-12 编码标准
    '@PSR12' => true,
    // 使用短数组语法 []
    'array_syntax' => ['syntax' => 'short'],
    'binary_operator_spaces' => [
        // 对于 => 运算符，使用单个空格对齐
        'operators' => ['=>' => 'align_single_space'],
    ],
    // 命名空间声明后插入空行
    'blank_line_after_namespace' => true,
    // PHP 开始标签后插入空行
    'blank_line_after_opening_tag' => true,
    // 在 return 语句前插入空行
    'blank_line_before_statement' => [
        'statements' => ['return'],
    ],
    'braces' => [
        // 函数和 OOP 构造后的大括号保持在同一行
        'position_after_functions_and_oop_constructs' => 'same',
        // 不允许单行闭包
        'allow_single_line_closure' => false,
    ],
    // 类型转换时使用单个空格
    'cast_spaces' => ['space' => 'single'],
    'class_attributes_separation' => [
        // 类属性和方法之间插入一个空行
        'elements' => ['method' => 'one'],
    ],
    // 字符串连接符号之间使用一个空格
    'concat_space' => ['spacing' => 'one'],
    // 声明语句中的等号两边不加空格
    'declare_equal_normalize' => ['space' => 'none'],
    // 闭包函数声明时加一个空格
    'function_declaration' => ['closure_function_spacing' => 'one'],
    // 规范 include 语句
    'include' => true,
    // 类型转换时使用小写
    'lowercase_cast' => true,
    // 删除多余的空行
    'no_extra_blank_lines' => [
        'tokens' => [
            'extra',
            // 删除 throw 语句前后的多余空行
            'throw',
            // 删除 use 语句前后的多余空行
            'use',
        ],
    ],
    // 数组偏移量周围不加空格
    'no_spaces_around_offset' => true,
    // 删除未使用的导入
    'no_unused_imports' => true,
    // not 运算符后不加空格
    'not_operator_with_successor_space' => false,
    // 按字母顺序排序导入
    'ordered_imports' => ['sort_algorithm' => 'alpha'],
    // PHPDoc 注释左对齐
    'phpdoc_align' => ['align' => 'left'],
    // PHPDoc 注释缩进
    'phpdoc_indent' => true,
    // 删除 PHPDoc 中的访问修饰符
    'phpdoc_no_access' => true,
    // 删除 PHPDoc 中的包声明
    'phpdoc_no_package' => true,
    // 删除无用的 inheritdoc
    'phpdoc_no_useless_inheritdoc' => true,
    // 使用 PHPDoc 标量类型
    'phpdoc_scalar' => true,
    // PHPDoc 单行变量注释间距
    'phpdoc_single_line_var_spacing' => true,
    // 不强制要求 PHPDoc 摘要
    'phpdoc_summary' => false,
    // 将 PHPDoc 转换为普通注释
    'phpdoc_to_comment' => true,
    // 修剪 PHPDoc 注释中的空白
    'phpdoc_trim' => true,
    // 规范 PHPDoc 类型
    'phpdoc_types' => true,
    // 文件末尾保留一个空行
    'single_blank_line_at_eof' => true,
    // 每个声明语句只包含一个类元素
    'single_class_element_per_statement' => ['elements' => ['property']],
    // 每个导入语句只包含一个导入
    'single_import_per_statement' => true,
    // 导入语句后插入一个空行
    'single_line_after_imports' => true,
    // 使用单引号
    'single_quote' => true,
    // 分号后加空格
    'space_after_semicolon' => true,
    // 使用标准的 not equals 符号 <>
    'standardize_not_equals' => true,
    // 三元运算符周围加空格
    'ternary_operator_spaces' => true,
    // 多行数组中使用拖尾逗号
    'trailing_comma_in_multiline' => ['elements' => ['arrays']],
    // 修剪数组中的空白
    'trim_array_spaces' => true,
    // 一元运算符后加空格
    'unary_operator_spaces' => true,
    // 数组中的逗号后加空格
    'whitespace_after_comma_in_array' => true,
])
    ->setFinder($finder)
    ->setUsingCache(false);
