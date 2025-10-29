# DEVELOPMENT TRACKER - Influencer Marketplace

## 📋 PRIORITÀ DI SVILUPPO

### 🚨 PRIORITÀ 1 - Fix Critici (Urgenti)
**Stato:** ✅ Completato
- [x] **FIX:** Influencer con candidatura esistente non può vedere dettagli campagna in pausa
  - **File:** `/infl/influencers/campaigns/view.php`
  - **Obiettivo:** Permettere visualizzazione SOLO se ha candidatura, mostrare "Campagna in fase di revisione"
  - **Implementazione:** 
    - Logica di accesso modificata per permettere visualizzazione campagne in pausa solo a influencer con candidatura esistente
    - UI aggiornata con stato "In fase di revisione" e badge giallo
    - Pulsante candidatura disabilitato per campagne in pausa
    - Mantenute tutte le funzionalità esistenti

### 🎯 PRIORITÀ 2 - Comunicazione Brand-Admin (Essenziali)
**Stato:** 📝 Pianificato
- [ ] **FEATURE:** Sistema richieste informazioni per campagne in pausa
  - **DB:** Creare tabella `campaign_pause_requests`
  - **Admin:** Form pausa con motivo, documenti richiesti, scadenza
  - **Brand:** Sezione "Richiesta integrazioni" in `campaign-details.php`
  - **Note:** Upload documenti + notifica base

### ⚡ PRIORITÀ 3 - Workflow Automatizzato (Miglioramenti)
**Stato:** 📋 Backlog
- [ ] **FEATURE:** Sistema notifiche base
  - **DB:** Tabella `notifications` semplice
  - **Notifiche:** Pausa campagna → Brand, Documenti caricati → Admin
- [ ] **FEATURE:** Scadenze automatiche campagne in pausa
  - **Cron job:** Controllo campagne in pausa oltre X giorni

### 🎨 PRIORITÀ 4 - Esperienza Utente (Raffinamenti)
**Stato:** 💡 Idee
- [ ] **UI/UX:** Stati visibili migliorati ("In attesa documenti", "In revisione amministrativa")
- [ ] **DASHBOARD:** Contatore admin campagne in pausa da X giorni

## 🔧 MODIFICHE IMPLEMENTATE

### ✅ Completate
- [x] **2024-XX-XX:** Fix critico - Influencer con candidatura vede campagne in pausa
  - **File:** `/infl/influencers/campaigns/view.php`
  - **Chat:** https://chat.deepseek.com/a/chat/s/2046c4b7-7166-45fc-9763-5e0ad3fadeb6
  - **Dettagli:** Risolto problema "Campagna non trovata" per influencer con candidatura esistente quando admin mette in pausa campagna

- [x] **2024-XX-XX:** Soft delete campagne - Filtro `deleted_at IS NULL` in listing influencer
  - **File:** `/infl/influencers/campaigns/list.php`
  - **Chat:** [Inserire link chat precedente]

## 💾 STRUTTURA DATABASE PIANIFICATA

### Tabelle da creare:
```sql
-- Per PRIORITÀ 2
campaign_pause_requests:
- id (PK)
- campaign_id (FK)
- admin_id (FK) 
- reason (TEXT)
- requested_docs (JSON)
- deadline (DATE)
- created_at
- resolved_at

-- Per PRIORITÀ 3  
notifications:
- id (PK)
- user_id (FK)
- type (campaign_paused, docs_uploaded, etc.)
- title
- message
- read_at
- created_at