<?php

declare(strict_types=1);

namespace ScadenzeFatture;

final class DashboardService
{
    /** @param InvoiceDue[] $dues */
    public function summarize(array $dues): array
    {
        $byPaymentType = [];
        $totalAmount = 0.0;

        foreach ($dues as $due) {
            $key = $due->paymentTypeCode;
            if (!isset($byPaymentType[$key])) {
                $byPaymentType[$key] = [
                    'label' => $due->paymentTypeLabel,
                    'count' => 0,
                    'amount' => 0.0,
                    'items' => [],
                ];
            }

            $byPaymentType[$key]['count']++;
            $byPaymentType[$key]['amount'] += $due->amount;
            $byPaymentType[$key]['items'][] = $due;
            $totalAmount += $due->amount;
        }

        ksort($byPaymentType);

        return [
            'total_dues' => count($dues),
            'total_amount' => $totalAmount,
            'by_payment_type' => $byPaymentType,
        ];
    }
}
