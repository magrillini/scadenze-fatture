<?php

declare(strict_types=1);

namespace ScadenzeFatture;

final class DashboardService
{
    /**
     * @param InvoiceDue[] $dues
     * @param array{due_statuses?:array<string,array{paid:bool,lawyer:bool}>, invoice_overrides?:array<string,array{installments:int}>} $registry
     * @return array<string,mixed>
     */
    public function summarize(array $dues, array $registry = [], string $customerGroupBy = 'cliente'): array
    {
        $normalizedDues = $this->normalizeDues($dues, $registry);
        $byPaymentType = [];
        $totalAmount = 0.0;
        $collectedAmount = 0.0;
        $legalAmount = 0.0;
        $today = date('Y-m-d');
        $customerStats = [];
        $agingBuckets = [
            'overdue_unpaid' => [
                'label' => 'Scadute non pagate',
                'color' => '#dc2626',
                'count' => 0,
                'amount' => 0.0,
            ],
            'due_soon' => [
                'label' => 'Entro 19 giorni',
                'color' => '#f97316',
                'count' => 0,
                'amount' => 0.0,
            ],
            'future' => [
                'label' => 'Oltre 20 giorni',
                'color' => '#16a34a',
                'count' => 0,
                'amount' => 0.0,
            ],
        ];

        foreach ($normalizedDues as $due) {
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

            if ($due->paid) {
                $collectedAmount += $due->amount;
            }

            if ($due->lawyer) {
                $legalAmount += $due->amount;
            }

            $customerKey = $this->resolveCustomerKey($due, $customerGroupBy);
            if (!isset($customerStats[$customerKey])) {
                $customerStats[$customerKey] = [
                    'client' => $due->clientName,
                    'cf' => $due->clientVat ?: '-',
                    'turnover' => 0.0,
                    'collected' => 0.0,
                    'outstanding' => 0.0,
                    'legal' => 0.0,
                    'overdue_unpaid' => 0,
                ];
            }

            $customerStats[$customerKey]['turnover'] += $due->amount;
            $customerStats[$customerKey]['collected'] += $due->paid ? $due->amount : 0.0;
            $customerStats[$customerKey]['outstanding'] += $due->paid ? 0.0 : $due->amount;
            $customerStats[$customerKey]['legal'] += $due->lawyer ? $due->amount : 0.0;
            if (!$due->paid && $due->dueDate < $today) {
                $customerStats[$customerKey]['overdue_unpaid']++;
            }

            $agingKey = $this->resolveAgingBucket($due, $today);
            $agingBuckets[$agingKey]['count']++;
            $agingBuckets[$agingKey]['amount'] += $due->amount;
        }

        foreach ($customerStats as &$customerStat) {
            $customerStat['stars'] = max(0, 5 - $customerStat['overdue_unpaid']);
        }
        unset($customerStat);

        usort(
            $customerStats,
            static fn (array $a, array $b): int => [$b['turnover'], $a['client']] <=> [$a['turnover'], $b['client']]
        );

        ksort($byPaymentType);
        $outstandingAmount = $totalAmount - $collectedAmount;

        return [
            'dues' => $normalizedDues,
            'total_dues' => count($normalizedDues),
            'total_amount' => $totalAmount,
            'collected_amount' => $collectedAmount,
            'outstanding_amount' => $outstandingAmount,
            'legal_amount' => $legalAmount,
            'by_payment_type' => $byPaymentType,
            'customers' => $customerStats,
            'aging_buckets' => $agingBuckets,
            'charts' => [
                'overview' => [
                    'Fatturato' => $totalAmount,
                    'Riscosso' => $collectedAmount,
                    'Da riscuotere' => $outstandingAmount,
                    'Avvocato' => $legalAmount,
                ],
                'by_customer' => $customerStats,
            ],
        ];
    }

    /** @param InvoiceDue[] $dues
      * @param array<string,mixed> $registry
      * @return InvoiceDue[] */
    private function normalizeDues(array $dues, array $registry): array
    {
        $grouped = [];
        foreach ($dues as $due) {
            $invoiceId = $this->buildInvoiceId($due);
            $grouped[$invoiceId][] = $due;
        }

        $normalized = [];
        foreach ($grouped as $invoiceId => $invoiceDues) {
            $installments = max(1, (int) ($registry['invoice_overrides'][$invoiceId]['installments'] ?? count($invoiceDues)));
            if ($installments > 1) {
                $normalized = [...$normalized, ...$this->splitInvoiceIntoInstallments($invoiceDues, $invoiceId, $installments, $registry)];
                continue;
            }

            foreach (array_values($invoiceDues) as $index => $due) {
                $dueId = $this->buildDueId($invoiceId, $index + 1, $due->dueDate, $due->amount);
                $status = $registry['due_statuses'][$dueId] ?? ['paid' => false, 'lawyer' => false];
                $normalized[] = $due
                    ->withInstallments($index + 1, count($invoiceDues), $due->amount, $due->dueDate, $dueId)
                    ->withStatus((bool) $status['paid'], (bool) $status['lawyer'], $dueId, $invoiceId);
            }
        }

        usort(
            $normalized,
            static fn (InvoiceDue $a, InvoiceDue $b): int => [$a->dueDate, $a->clientName, $a->installmentNumber] <=> [$b->dueDate, $b->clientName, $b->installmentNumber]
        );

        return $normalized;
    }

    /** @param InvoiceDue[] $invoiceDues
      * @param array<string,mixed> $registry
      * @return InvoiceDue[] */
    private function splitInvoiceIntoInstallments(array $invoiceDues, string $invoiceId, int $installments, array $registry): array
    {
        $firstDue = $invoiceDues[0];
        $total = array_reduce($invoiceDues, static fn (float $carry, InvoiceDue $due): float => $carry + $due->amount, 0.0);
        $baseAmount = round($total / $installments, 2);
        $items = [];
        $baseDate = $firstDue->dueDate !== '' ? $firstDue->dueDate : $firstDue->invoiceDate;
        $allocated = 0.0;

        for ($index = 1; $index <= $installments; $index++) {
            $amount = $index === $installments ? round($total - $allocated, 2) : $baseAmount;
            $allocated += $amount;
            $dueDate = date('Y-m-d', strtotime(sprintf('%s +%d month', $baseDate, $index - 1)) ?: time());
            $dueId = $this->buildDueId($invoiceId, $index, $dueDate, $amount);
            $status = $registry['due_statuses'][$dueId] ?? ['paid' => false, 'lawyer' => false];
            $items[] = $firstDue
                ->withInstallments($index, $installments, $amount, $dueDate, $dueId)
                ->withStatus((bool) $status['paid'], (bool) $status['lawyer'], $dueId, $invoiceId);
        }

        return $items;
    }

    private function buildInvoiceId(InvoiceDue $due): string
    {
        return sha1(implode('|', [$due->invoiceNumber, $due->invoiceDate, $due->clientName, $due->clientVat ?? '-']));
    }

    private function buildDueId(string $invoiceId, int $installmentNumber, string $dueDate, float $amount): string
    {
        return sha1(implode('|', [$invoiceId, $installmentNumber, $dueDate, number_format($amount, 2, '.', '')]));
    }

    private function resolveAgingBucket(InvoiceDue $due, string $today): string
    {
        if (!$due->paid && $due->dueDate < $today) {
            return 'overdue_unpaid';
        }

        $dueTimestamp = strtotime($due->dueDate);
        $todayTimestamp = strtotime($today);
        if ($dueTimestamp === false || $todayTimestamp === false) {
            return 'future';
        }

        $daysUntilDue = (int) floor(($dueTimestamp - $todayTimestamp) / 86400);

        if ($daysUntilDue <= 19) {
            return 'due_soon';
        }

        return 'future';
    }

    private function resolveCustomerKey(InvoiceDue $due, string $customerGroupBy): string
    {
        if ($customerGroupBy === 'cf') {
            $vat = trim((string) ($due->clientVat ?? ''));
            if ($vat !== '') {
                return 'cf:' . mb_strtolower($vat);
            }
        }

        $clientName = trim($due->clientName);
        if ($clientName !== '') {
            return 'cliente:' . mb_strtolower($clientName);
        }

        return 'fallback:' . sha1(implode('|', [$due->invoiceNumber, $due->invoiceDate]));
    }
}

