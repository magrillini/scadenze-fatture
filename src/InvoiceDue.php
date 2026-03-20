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
        public readonly ?string $email
    ) {
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
