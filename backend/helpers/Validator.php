<?php
/**
 * NOVA Messenger - Input Validator
 */

declare(strict_types=1);

class Validator
{
    private array $errors = [];
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data): self
    {
        return new self($data);
    }

    public function required(string $field, string $label = ''): self
    {
        $label = $label ?: $field;
        if (!isset($this->data[$field]) || trim((string)$this->data[$field]) === '') {
            $this->errors[$field] = "حقل {$label} مطلوب";
        }
        return $this;
    }

    public function phone(string $field, string $label = ''): self
    {
        $label = $label ?: $field;
        if (isset($this->data[$field]) && !preg_match('/^\+?[0-9]{7,20}$/', $this->data[$field])) {
            $this->errors[$field] = "رقم الهاتف في حقل {$label} غير صحيح";
        }
        return $this;
    }

    public function email(string $field, string $label = ''): self
    {
        $label = $label ?: $field;
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "البريد الإلكتروني في حقل {$label} غير صحيح";
        }
        return $this;
    }

    public function minLength(string $field, int $min, string $label = ''): self
    {
        $label = $label ?: $field;
        if (isset($this->data[$field]) && mb_strlen((string)$this->data[$field]) < $min) {
            $this->errors[$field] = "حقل {$label} يجب أن يكون {$min} أحرف على الأقل";
        }
        return $this;
    }

    public function maxLength(string $field, int $max, string $label = ''): self
    {
        $label = $label ?: $field;
        if (isset($this->data[$field]) && mb_strlen((string)$this->data[$field]) > $max) {
            $this->errors[$field] = "حقل {$label} يجب ألا يتجاوز {$max} حرفاً";
        }
        return $this;
    }

    public function in(string $field, array $allowed, string $label = ''): self
    {
        $label = $label ?: $field;
        if (isset($this->data[$field]) && !in_array($this->data[$field], $allowed, true)) {
            $this->errors[$field] = "قيمة حقل {$label} غير مسموح بها";
        }
        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function setError(string $field, string $message): self
    {
        $this->errors[$field] = $message;
        return $this;
    }

    public function get(string $field, mixed $default = null): mixed
    {
        return $this->data[$field] ?? $default;
    }

    public function sanitizeString(string $field): string
    {
        return htmlspecialchars(strip_tags(trim((string)($this->data[$field] ?? ''))), ENT_QUOTES, 'UTF-8');
    }
}
