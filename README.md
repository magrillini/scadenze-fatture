# Scadenze Fatture XML

Applicazione PHP pensata per un server LAMP locale su Ubuntu 24.04 che:

- legge le fatture XML attive in formato FatturaPA;
- ricava scadenze e importi dai blocchi `DatiPagamento/DettaglioPagamento`;
- suddivide le scadenze per tipo di pagamento;
- costruisce uno scadenzario generale;
- arricchisce i dati con telefono e mail del cliente da un CSV di appoggio;
- invia le scadenze a Google Calendar tramite API OAuth 2.

## Requisiti

- PHP 8.2+
- Estensioni PHP standard per XML/DOM

## Installazione

```bash
cp config/google-calendar.local.json.example config/google-calendar.local.json
```

## Configurazione contatti

Prepara un CSV separato da `;` con intestazioni:

```text
cliente;piva;codice_fiscale;telefono;email
```

Il matching avviene in questo ordine:

1. partita IVA del cliente in fattura;
2. codice fiscale del cliente;
3. denominazione / nominativo cliente.

## Avvio su LAMP o in sviluppo

Con Apache imposta `DocumentRoot` sulla cartella `public/`.

In locale puoi provare anche con il server integrato PHP:

```bash
php -S 127.0.0.1:8080 -t public
```

Apri poi `http://127.0.0.1:8080`.

## Google Calendar API

1. Crea credenziali OAuth 2.0 in Google Cloud.
2. Inserisci `client_id` e `client_secret` in `config/google-calendar.local.json`.
3. Aggiungi come redirect URI l'URL della tua installazione, ad esempio:
   `http://localhost/scadenze-fatture/index.php?action=oauth_callback`
4. Dalla dashboard clicca **Collega Google Calendar**.
5. Dopo l'autorizzazione, il token sarà salvato in `storage/google-token.json`.
6. Clicca **Invia scadenze a Google** per creare gli eventi.

## Struttura

- `public/index.php`: dashboard web.
- `src/InvoiceParser.php`: parser XML FatturaPA e mapping pagamenti.
- `src/ContactsRepository.php`: import rubrica clienti da CSV.
- `src/DashboardService.php`: aggregazioni per lo scadenzario.
- `src/GoogleCalendarService.php`: integrazione API Google Calendar.
- `samples/`: esempi pronti per test rapidi.
- `tests/smoke.php`: smoke test CLI.
