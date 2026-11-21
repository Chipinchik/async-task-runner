<?php

namespace Tests\Unit;

use App\Services\TaskProcessor;
use PHPUnit\Framework\TestCase;

class TaskProcessorTest extends TestCase
{
    public function test_unicode_text_statistics(): void
    {
        $result = (new TaskProcessor)->text("Привет, мир!\nПРИВЕТ world world.");
        $this->assertSame(5, $result['words']);
        $this->assertSame(3, $result['unique_words']);
        $this->assertSame(2, $result['lines']);
        $this->assertSame(['word' => 'привет', 'count' => 2], $result['top_words'][0]);
        $this->assertSame(mb_strlen("Привет, мир!\nПРИВЕТ world world."), $result['characters']);
    }

    public function test_csv_numeric_and_empty_statistics_with_bom_and_quoted_values(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'taskflow');
        file_put_contents($path, "\xEF\xBB\xBFname;value;missing\n\"Иван; Иванов\";2;\nАнна;4;\nБорис;;\n");
        try {
            $result = (new TaskProcessor)->csv($path, ';', fn () => null);
            $this->assertSame(3, $result['rows']);
            $this->assertSame(3, $result['column_count']);
            $this->assertSame(4, $result['empty_values']);
            $this->assertEquals(['min' => 2, 'max' => 4, 'sum' => 6, 'avg' => 3], array_intersect_key($result['columns'][1], array_flip(['min', 'max', 'sum', 'avg'])));
            $this->assertFalse($result['columns'][0]['is_numeric']);
        } finally {
            unlink($path);
        }
    }

    public function test_malformed_csv_is_rejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'taskflow');
        file_put_contents($path, "a,b\n1,2,3\n");
        try {
            $this->expectException(\RuntimeException::class);
            (new TaskProcessor)->csv($path, ',', fn () => null);
        } finally {
            unlink($path);
        }
    }

    public function test_duplicate_header_is_rejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'taskflow');
        file_put_contents($path, "a,a\n1,2\n");
        try {
            $this->expectException(\RuntimeException::class);
            (new TaskProcessor)->csv($path, ',', fn () => null);
        } finally {
            unlink($path);
        }
    }

    public function test_non_utf8_csv_is_rejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'taskflow');
        file_put_contents($path, "name\n\xFF\n");
        try {
            $this->expectException(\RuntimeException::class);
            (new TaskProcessor)->csv($path, ',', fn () => null);
        } finally {
            unlink($path);
        }
    }
}
