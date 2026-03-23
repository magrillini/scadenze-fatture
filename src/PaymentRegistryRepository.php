<?php

declare(strict_types=1);

namespace ScadenzeFatture;

final class PaymentRegistryRepository
{
    public function __construct(private readonly string $path)
    {
    }

    /** @return array{due_statuses:array<string,array<string,mixed>>, invoice_overrides:array<string,array{installments:int}>} */
    public function load(): array
    {
        if (!is_file($this->path)) {
            return [
                'due_statuses' => [],
                'invoice_overrides' => [],
            ];
        }

        $decoded = json_decode((string) file_get_contents($this->path), true);
        if (!is_array($decoded)) {
            return [
                'due_statuses' => [],
                'invoice_overrides' => [],
            ];
        }

        return [
            'due_statuses' => is_array($decoded['due_statuses'] ?? null) ? $decoded['due_statuses'] : [],
            'invoice_overrides' => is_array($decoded['invoice_overrides'] ?? null) ? $decoded['invoice_overrides'] : [],
        ];
    }

    /** @param array<string,mixed> $data */
    public function save(array $data): void
    {
        $directory = dirname($this->path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents(
            $this->path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );
    }

    public function updateDueStatus(
        string $dueId,
        bool $paid,
        bool $lawyer,
        ?string $paymentMethod = null,
        ?string $paymentDate = null,
        ?float $paymentAmount = null,
        ?string $paymentNote = null
    ): void
    {
        $data = $this->load();
        if ($paymentMethod === 'contanti' && $paymentAmount !== null && $paymentAmount > 3000.0) {
            throw new \RuntimeException('Il pagamento in contanti non può superare 3.000,00 €.');
        }

        $data['due_statuses'][$dueId] = [
            'paid' => $paid,
            'lawyer' => $lawyer,
            'payment_method' => $paymentMethod,
            'payment_date' => $paymentDate,
            'payment_amount' => $paymentAmount,
            'payment_note' => $paymentNote,
        ];
        $this->save($data);
    }

    public function updateInvoiceInstallments(string $invoiceId, int $installments): void
    {
        $data = $this->load();
        $data['invoice_overrides'][$invoiceId] = ['installments' => max(1, min(24, $installments))];
        $this->save($data);
    }
}
