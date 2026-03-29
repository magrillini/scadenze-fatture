<?php

declare(strict_types=1);

namespace ScadenzeFatture;

final class InvoiceDue
{
    public function __construct(
        public readonly string $invoiceNumber,
        public readonly string $invoiceDate,
        public readonly string $clientName,
        public readonly ?string $clientVat,
        public readonly float $amount,
        public readonly string $paymentTypeCode,
        public readonly string $paymentTypeLabel,
        public readonly string $dueDate,
        public readonly ?string $phone,
        public readonly ?string $email,
        public readonly int $installmentNumber = 1,
        public readonly int $installmentCount = 1,
        public readonly bool $paid = false,
        public readonly bool $lawyer = false,
        public readonly ?string $dueId = null,
        public readonly ?string $invoiceId = null,
    ) {
    }

    public function withStatus(bool $paid, bool $lawyer, ?string $dueId = null, ?string $invoiceId = null): self
    {
        return new self(
            invoiceNumber: $this->invoiceNumber,
            invoiceDate: $this->invoiceDate,
            clientName: $this->clientName,
            clientVat: $this->clientVat,
            amount: $this->amount,
            paymentTypeCode: $this->paymentTypeCode,
            paymentTypeLabel: $this->paymentTypeLabel,
            dueDate: $this->dueDate,
            phone: $this->phone,
            email: $this->email,
            installmentNumber: $this->installmentNumber,
            installmentCount: $this->installmentCount,
            paid: $paid,
            lawyer: $lawyer,
            dueId: $dueId ?? $this->dueId,
            invoiceId: $invoiceId ?? $this->invoiceId,
        );
    }

    public function withInstallments(int $installmentNumber, int $installmentCount, float $amount, string $dueDate, ?string $dueId = null): self
    {
        return new self(
            invoiceNumber: $this->invoiceNumber,
            invoiceDate: $this->invoiceDate,
            clientName: $this->clientName,
            clientVat: $this->clientVat,
            amount: $amount,
            paymentTypeCode: $this->paymentTypeCode,
            paymentTypeLabel: $this->paymentTypeLabel,
            dueDate: $dueDate,
            phone: $this->phone,
            email: $this->email,
            installmentNumber: $installmentNumber,
            installmentCount: $installmentCount,
            paid: $this->paid,
            lawyer: $this->lawyer,
            dueId: $dueId ?? $this->dueId,
            invoiceId: $this->invoiceId,
        );
    }

    public function toCalendarSummary(): string
    {
        return sprintf(
            'Scadenza fattura %s - %s - %s',
            $this->invoiceNumber,
            $this->clientName,
            number_format($this->amount, 2, ',', '.') . ' €'
        );
    }

    public function toCalendarDescription(): string
    {
        $lines = [
            'Cliente: ' . $this->clientName,
            'Fattura: ' . $this->invoiceNumber,
            'Data fattura: ' . $this->invoiceDate,
            'Importo: ' . number_format($this->amount, 2, ',', '.') . ' €',
            'Tipo pagamento: ' . $this->paymentTypeLabel . ' (' . $this->paymentTypeCode . ')',
            'Rata: ' . $this->installmentNumber . '/' . $this->installmentCount,
            'Pagata: ' . ($this->paid ? 'Sì' : 'No'),
            'Avvocato: ' . ($this->lawyer ? 'Sì' : 'No'),
        ];

        if ($this->phone) {
            $lines[] = 'Telefono: ' . $this->phone;
        }

        if ($this->email) {
            $lines[] = 'Email: ' . $this->email;
        }

        return implode("\n", $lines);
    }
}

