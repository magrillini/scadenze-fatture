<?php

declare(strict_types=1);

namespace ScadenzeFatture;

use DOMDocument;
use DOMXPath;
use RuntimeException;

final class InvoiceParser
{
    private const PAYMENT_TYPES = [
        'MP01' => 'Contanti',
        'MP02' => 'Assegno',
        'MP03' => 'Assegno circolare',
        'MP04' => 'Contanti presso tesoreria',
        'MP05' => 'Bonifico',
        'MP06' => 'Vaglia cambiario',
        'MP07' => 'Bollettino bancario',
        'MP08' => 'Carta di pagamento',
        'MP09' => 'RID',
        'MP10' => 'RID utenze',
        'MP11' => 'RID veloce',
        'MP12' => 'RIBA',
        'MP13' => 'MAV',
        'MP14' => 'Quietanza erario',
        'MP15' => 'Giroconto',
        'MP16' => 'Domiciliazione bancaria',
        'MP17' => 'Domiciliazione postale',
        'MP18' => 'Bollettino di c/c postale',
        'MP19' => 'SEPA direct debit',
        'MP20' => 'SEPA direct debit CORE',
        'MP21' => 'SEPA direct debit B2B',
        'MP22' => 'Trattenuta su somme riscosse',
        'MP23' => 'PagoPA',
    ];

    /** @param array<string, array{name:string, phone:?string, email:?string}> $contacts
      * @return InvoiceDue[] */
    public function parseDirectory(string $directory, array $contacts = []): array
    {
        if (!is_dir($directory)) {
            throw new RuntimeException('La directory XML non esiste: ' . $directory);
        }

        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.xml') ?: [];
        $dues = [];

        foreach ($files as $file) {
            $dues = [...$dues, ...$this->parseFile($file, $contacts)];
        }

        usort(
            $dues,
            static fn (InvoiceDue $a, InvoiceDue $b): int => [$a->dueDate, $a->clientName] <=> [$b->dueDate, $b->clientName]
        );

        return $dues;
    }

    /** @param array<string, array{name:string, phone:?string, email:?string}> $contacts
      * @return InvoiceDue[] */
    public function parseFile(string $path, array $contacts = []): array
    {
        $document = new DOMDocument();
        $loaded = @$document->load($path);

        if (!$loaded) {
            throw new RuntimeException('Impossibile leggere il file XML: ' . $path);
        }

        $xpath = new DOMXPath($document);

        $invoiceNumber = $this->stringValue($xpath, 'string(//*[local-name()="DatiGeneraliDocumento"]/*[local-name()="Numero"])');
        $invoiceDate = $this->stringValue($xpath, 'string(//*[local-name()="DatiGeneraliDocumento"]/*[local-name()="Data"])');
        $clientName = $this->stringValue($xpath, 'string(//*[local-name()="CessionarioCommittente"]//*[local-name()="Anagrafica"]/*[local-name()="Denominazione"])');
        if ($clientName === '') {
            $clientName = trim(
                $this->stringValue($xpath, 'string(//*[local-name()="CessionarioCommittente"]//*[local-name()="Anagrafica"]/*[local-name()="Nome"])')
                . ' ' .
                $this->stringValue($xpath, 'string(//*[local-name()="CessionarioCommittente"]//*[local-name()="Anagrafica"]/*[local-name()="Cognome"])')
            );
        }

        $vat = $this->stringValue($xpath, 'string(//*[local-name()="CessionarioCommittente"]//*[local-name()="IdFiscaleIVA"]/*[local-name()="IdCodice"])');
        $taxCode = $this->stringValue($xpath, 'string(//*[local-name()="CessionarioCommittente"]//*[local-name()="DatiAnagrafici"]/*[local-name()="CodiceFiscale"])');
        $contact = $contacts[ContactsRepository::normalizeKey($vat)]
            ?? $contacts[ContactsRepository::normalizeKey($taxCode)]
            ?? $contacts[ContactsRepository::normalizeKey($clientName)]
            ?? ['name' => $clientName, 'phone' => null, 'email' => null];

        $dues = [];
        $paymentDetails = $xpath->query('//*[local-name()="DatiPagamento"]/*[local-name()="DettaglioPagamento"]');
        if ($paymentDetails === false || $paymentDetails->length === 0) {
            $total = (float) $this->stringValue($xpath, 'string(//*[local-name()="DatiGeneraliDocumento"]/*[local-name()="ImportoTotaleDocumento"])', '0');
            $dues[] = new InvoiceDue(
                invoiceNumber: $invoiceNumber,
                invoiceDate: $invoiceDate,
                clientName: $contact['name'] ?: $clientName,
                clientVat: $vat ?: $taxCode,
                amount: $total,
                paymentTypeCode: 'ND',
                paymentTypeLabel: 'Non definito',
                dueDate: $invoiceDate,
                phone: $contact['phone'],
                email: $contact['email'],
            );

            return $dues;
        }

        foreach ($paymentDetails as $detail) {
            $paymentTypeCode = $this->nodeValue($xpath, 'string(./*[local-name()="ModalitaPagamento"])', $detail, 'ND');
            $amount = (float) $this->nodeValue($xpath, 'string(./*[local-name()="ImportoPagamento"])', $detail, '0');
            $dueDate = $this->nodeValue($xpath, 'string(./*[local-name()="DataScadenzaPagamento"])', $detail, $invoiceDate);
            $dues[] = new InvoiceDue(
                invoiceNumber: $invoiceNumber,
                invoiceDate: $invoiceDate,
                clientName: $contact['name'] ?: $clientName,
                clientVat: $vat ?: $taxCode,
                amount: $amount,
                paymentTypeCode: $paymentTypeCode,
                paymentTypeLabel: self::PAYMENT_TYPES[$paymentTypeCode] ?? 'Tipo non mappato',
                dueDate: $dueDate,
                phone: $contact['phone'],
                email: $contact['email'],
            );
        }

        return $dues;
    }

    private function stringValue(DOMXPath $xpath, string $expression, string $default = ''): string
    {
        $value = trim((string) $xpath->evaluate($expression));
        return $value !== '' ? $value : $default;
    }

    private function nodeValue(DOMXPath $xpath, string $expression, \DOMNode $contextNode, string $default = ''): string
    {
        $value = trim((string) $xpath->evaluate($expression, $contextNode));
        return $value !== '' ? $value : $default;
    }
}

