# Asraa Realty CRM — Architecture & Implementation Guide

> **Plugin Version:** 3.0.0  
> **Prepared:** 2026-03-14  
> **Scope:** Scalable Real Estate CRM with multi-level agent hierarchy, lead pipeline, property management, deal tracking, commission system, and automation workflows.

---

## 1. System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         ASRAA REALTY PLATFORM                           │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                       WordPress Core                            │   │
│  │  ┌──────────────┐  ┌──────────────┐  ┌───────────────────────┐  │   │
│  │  │  wp-realestate│  │  Homeo Theme │  │   WooCommerce Listings│  │   │
│  │  │  (Properties, │  │  (Frontend,  │  │   (Monetisation)      │  │   │
│  │  │  Agents,      │  │   Elementor  │  └───────────────────────┘  │   │
│  │  │  Agencies)    │  │   Widgets)   │                             │   │
│  │  └──────┬───────┘  └──────────────┘                             │   │
│  │         │                                                        │   │
│  │  ┌──────▼──────────────────────────────────────────────────┐    │   │
│  │  │               ASRAA CRM PLUGIN v3.0.0                   │    │   │
│  │  │                                                          │    │   │
│  │  │  ┌─────────────┐  ┌────────────────┐  ┌─────────────┐  │    │   │
│  │  │  │  Agent       │  │  Lead Pipeline  │  │  Property   │  │    │   │
│  │  │  │  Hierarchy   │  │  (Stages/Groups)│  │  Management │  │    │   │
│  │  │  └─────────────┘  └────────────────┘  └─────────────┘  │    │   │
│  │  │                                                          │    │   │
│  │  │  ┌─────────────┐  ┌────────────────┐  ┌─────────────┐  │    │   │
│  │  │  │  Deal        │  │  Commission     │  │  Automation │  │    │   │
│  │  │  │  Tracking    │  │  System         │  │  Workflows  │  │    │   │
│  │  │  └─────────────┘  └────────────────┘  └─────────────┘  │    │   │
│  │  │                                                          │    │   │
│  │  │  ┌──────────────────────────────────────────────────┐   │    │   │
│  │  │  │              MVC Architecture                    │   │    │   │
│  │  │  │  Controllers → Services → Repositories → DB      │   │    │   │
│  │  │  └──────────────────────────────────────────────────┘   │    │   │
│  │  └──────────────────────────────────────────────────────────┘    │   │
│  │                                                                    │   │
│  │  ┌─────────────────┐  ┌─────────────────┐  ┌──────────────────┐  │   │
│  │  │  Asraa Smart    │  │  Asraa Property  │  │  Integrations    │  │   │
│  │  │  Valuation v3   │  │  Expiry (MU)     │  │  (WhatsApp/Email │  │   │
│  │  │  (AI + Maps)    │  │  (Cron/Logs)     │  │   Mailchimp/CF7) │  │   │
│  │  └─────────────────┘  └─────────────────┘  └──────────────────┘  │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  External Services: Google Maps API · WhatsApp Business · SMTP          │
└─────────────────────────────────────────────────────────────────────────┘
```

### Data Flow

```
Website Visitor
      │
      ▼ (Contact Form 7 / Lead Intake)
┌─────────────┐
│  Lead Entry  │──► Lead Scoring ──► Stage Assignment ──► Agent Assignment
└──────┬──────┘
       │
       ▼ (Follow-up / Nurture)
┌─────────────┐
│  Lead Pipeline│ (Stages: New → Contacted → Qualified → Proposal → Converted)
└──────┬──────┘
       │
       ▼ (Property Match)
┌─────────────┐
│  Deal Created│──► Deal Stage (Prospect → Negotiation → Contract → Closed Won)
└──────┬──────┘
       │
       ▼ (On Closed Won)
┌─────────────┐
│  Commission  │──► Auto-calculated → Pending → Paid
│  Generated   │
└──────┬──────┘
       │
       ▼ (Throughout)
┌─────────────┐
│  Automation  │──► Send Email / WhatsApp / Add Follow-up / Reassign Agent
│  Workflows   │
└─────────────┘
```

---

## 2. Database Schema

### Existing Tables (v2.1.0)

| Table | Purpose |
|---|---|
| `wp_asraa_crm_leads` | Lead records (name, email, phone, status, stage_id, group_id, assigned_to) |
| `wp_asraa_crm_followups` | Follow-up tasks per lead |
| `wp_asraa_crm_notes` | Notes / activity log per lead |
| `wp_asraa_crm_stages` | Pipeline stage definitions |
| `wp_asraa_crm_groups` | Lead groups (Client, Agent, Developer) |
| `wp_asraa_crm_whatsapp_templates` | WhatsApp message templates |
| `wp_asraa_crm_email_templates` | Email templates |
| `wp_asraa_crm_message_log` | All sent messages (WhatsApp + Email) |
| `wp_asraa_crm_bulk_campaigns` | Bulk messaging campaigns |
| `wp_asraa_crm_properties` | CRM property records |

### New Tables (v3.0.0)

#### `wp_asraa_crm_agent_hierarchy`
```sql
CREATE TABLE wp_asraa_crm_agent_hierarchy (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL UNIQUE,   -- WP user ID
    manager_id  BIGINT UNSIGNED DEFAULT NULL,       -- Parent in hierarchy
    role        VARCHAR(50)  DEFAULT 'agent',       -- ceo|director|team_leader|senior_agent|agent|junior_agent
    level       INT          DEFAULT 1,             -- Depth (1=top)
    sort_order  INT          DEFAULT 0,
    created_at  DATETIME     NOT NULL,
    INDEX (manager_id)
);
```

#### `wp_asraa_crm_deals`
```sql
CREATE TABLE wp_asraa_crm_deals (
    id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lead_id              BIGINT UNSIGNED DEFAULT NULL,
    property_id          BIGINT UNSIGNED DEFAULT NULL,
    agent_id             BIGINT UNSIGNED NOT NULL,
    title                VARCHAR(255)    NOT NULL,
    deal_value           DECIMAL(15,2)   DEFAULT 0,
    stage                VARCHAR(30)     DEFAULT 'prospect',  -- prospect|negotiation|contract|closed_won|closed_lost
    expected_close_date  DATE            DEFAULT NULL,
    commission_plan_id   BIGINT UNSIGNED DEFAULT NULL,
    notes                TEXT            DEFAULT NULL,
    created_at           DATETIME        NOT NULL,
    updated_at           DATETIME        NOT NULL,
    INDEX (lead_id), INDEX (agent_id), INDEX (stage)
);
```

#### `wp_asraa_crm_deal_activities`
```sql
CREATE TABLE wp_asraa_crm_deal_activities (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    deal_id     BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NOT NULL,
    action      VARCHAR(50)  NOT NULL,    -- created|stage_changed|note_added|etc.
    description TEXT         DEFAULT NULL,
    created_at  DATETIME     NOT NULL,
    INDEX (deal_id)
);
```

#### `wp_asraa_crm_commission_plans`
```sql
CREATE TABLE wp_asraa_crm_commission_plans (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_name   VARCHAR(255)  NOT NULL,
    type        VARCHAR(20)   DEFAULT 'percentage',  -- percentage|flat
    rate        DECIMAL(10,4) DEFAULT 2.0000,
    description TEXT          DEFAULT NULL,
    created_at  DATETIME      NOT NULL
);
```

#### `wp_asraa_crm_commissions`
```sql
CREATE TABLE wp_asraa_crm_commissions (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    deal_id           BIGINT UNSIGNED NOT NULL,
    agent_id          BIGINT UNSIGNED NOT NULL,
    plan_id           BIGINT UNSIGNED DEFAULT NULL,
    commission_amount DECIMAL(15,2)   DEFAULT 0,
    commission_rate   DECIMAL(10,4)   DEFAULT 0,
    deal_value        DECIMAL(15,2)   DEFAULT 0,
    status            VARCHAR(20)     DEFAULT 'pending',  -- pending|paid
    paid_at           DATETIME        DEFAULT NULL,
    created_at        DATETIME        NOT NULL,
    INDEX (deal_id), INDEX (agent_id), INDEX (status)
);
```

#### `wp_asraa_crm_automation_rules`
```sql
CREATE TABLE wp_asraa_crm_automation_rules (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rule_name      VARCHAR(255) NOT NULL,
    trigger_event  VARCHAR(50)  NOT NULL,  -- lead_created|deal_won|deal_stage_changed|etc.
    conditions     LONGTEXT     DEFAULT NULL,  -- JSON array of condition objects
    actions        LONGTEXT     NOT NULL,      -- JSON array of action objects
    is_active      TINYINT(1)   DEFAULT 1,
    created_at     DATETIME     NOT NULL,
    INDEX (trigger_event)
);
```

#### `wp_asraa_crm_automation_logs`
```sql
CREATE TABLE wp_asraa_crm_automation_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rule_id     BIGINT UNSIGNED NOT NULL,
    trigger     VARCHAR(50)  NOT NULL,
    context     LONGTEXT     DEFAULT NULL,  -- JSON context data
    status      VARCHAR(20)  DEFAULT 'success',  -- success|error
    notes       TEXT         DEFAULT NULL,
    executed_at DATETIME     NOT NULL,
    INDEX (rule_id), INDEX (executed_at)
);
```

### Entity-Relationship Summary

```
wp_users (WP Core)
    │
    ├─[1:1]─► wp_asraa_crm_agent_hierarchy (user_id, manager_id → self)
    │
    └─[1:N]─► wp_asraa_crm_leads (assigned_to)
                    │
                    ├─[1:N]─► wp_asraa_crm_followups
                    ├─[1:N]─► wp_asraa_crm_notes
                    └─[1:N]─► wp_asraa_crm_deals (lead_id)
                                    │
                                    └─[1:N]─► wp_asraa_crm_deal_activities
                                    └─[1:1]─► wp_asraa_crm_commissions (deal_id)
                                                    │
                                                    └─[N:1]─► wp_asraa_crm_commission_plans

wp_asraa_crm_automation_rules
    └─[1:N]─► wp_asraa_crm_automation_logs
```

---

## 3. Plugin Folder Structure

```
asraa-crm-plugin-main/
├── asraa-crm.php                    # Main plugin file (bootstrap, DB install, menus)
├── composer.json
├── README.md
├── ARCHITECTURE.md                  # ← This document
│
├── admin/
│   ├── class-campaign-dashboard.php # Bulk campaign UI class
│   └── pages/
│       ├── dashboard.php            # CRM overview dashboard
│       ├── leads.php                # Lead list (DataTables)
│       ├── leads-add.php            # Add lead form
│       ├── leads-import.php         # CSV import
│       ├── lead-view.php            # Single lead detail / edit
│       ├── followups.php            # Follow-up task list
│       ├── notes.php                # Notes per lead
│       ├── properties.php           # CRM property list
│       ├── groups.php               # Lead groups list
│       ├── groups-add.php           # Add group
│       ├── groups-edit.php          # Edit group
│       ├── email-templates.php      # Email template CRUD
│       ├── whatsapp-templates.php   # WhatsApp template CRUD
│       ├── deals.php                # ★ Deal pipeline (NEW v3.0)
│       ├── commissions.php          # ★ Commission management (NEW v3.0)
│       ├── automation.php           # ★ Automation rules builder (NEW v3.0)
│       └── agent-hierarchy.php      # ★ Agent org-chart (NEW v3.0)
│
├── assets/
│   ├── css/
│   │   ├── crm-enhanced.css         # Main CRM stylesheet
│   │   └── groups.css               # Groups-specific styles
│   └── js/
│       ├── crm-enhanced.js          # AJAX & UI interactions
│       └── properties.js            # Properties DataTable
│
└── includes/
    ├── repositories/                # Data layer — direct DB access only
    │   ├── lead-repository.php
    │   ├── followup-repository.php
    │   ├── note-repository.php
    │   ├── stage-repository.php
    │   ├── whatsapp-template-repository.php
    │   ├── email-template-repository.php
    │   ├── property-repository.php
    │   ├── message-log-repository.php
    │   ├── bulk-campaign-repository.php
    │   ├── deal-repository.php          # ★ NEW v3.0
    │   ├── commission-repository.php    # ★ NEW v3.0
    │   ├── automation-repository.php    # ★ NEW v3.0
    │   └── agent-hierarchy-repository.php # ★ NEW v3.0
    │
    ├── services/                    # Business logic layer
    │   ├── lead-scoring-service.php
    │   ├── notification-service.php
    │   ├── property-service.php
    │   ├── messaging-service.php
    │   ├── deal-service.php             # ★ NEW v3.0
    │   ├── commission-service.php       # ★ NEW v3.0
    │   └── automation-service.php       # ★ NEW v3.0
    │
    └── controllers/                 # Presentation + WordPress hook layer
        ├── dashboard-controller.php
        ├── lead-controller.php
        ├── followup-controller.php
        ├── lead-intake.php
        ├── ajax-controller.php
        ├── property-controller.php
        ├── deal-controller.php          # ★ NEW v3.0
        ├── commission-controller.php    # ★ NEW v3.0
        └── automation-controller.php    # ★ NEW v3.0
```

---

## 4. Admin Dashboard Modules

### Module Map

```
Asraa CRM (WP Admin Sidebar, position 25)
├── 📊 Dashboard           — KPI cards: leads, follow-ups, converted, unassigned
├── 👥 Leads               — Lead list with filtering, sorting, bulk actions
│   ├── Add Lead           — Manual lead creation form
│   └── Import Leads       — CSV bulk import
├── 📅 Follow-ups          — Task calendar and overdue alerts
├── 🏠 Properties          — CRM property records (linked to WP posts)
├── 💼 Deals          ★    — Kanban pipeline: Prospect→Negotiation→Contract→Won/Lost
├── 💰 Commissions    ★    — Commission records, agent summaries, commission plans
├── ⚡ Automation     ★    — Workflow rules builder with trigger→action mapping
├── 🏢 Agent Hierarchy ★   — Multi-level org chart (CEO→Director→TL→Agent)
├── 📧 Email Templates     — Reusable email templates with variable substitution
├── 💬 WhatsApp Templates  — WhatsApp message templates
├── 📢 Campaigns           — Bulk WhatsApp / Email campaign sender
└── 🗂️ Groups              — Lead group management (Client/Agent/Developer)
```

★ = New in v3.0.0

### Dashboard Widgets

| Widget | Metric | Role |
|---|---|---|
| Total Leads | Count by agent or all | All |
| New Leads Today | Today's intake | All |
| Pending Follow-ups | Undone tasks | All |
| Follow-ups Today | Due today | All |
| Converted Leads | Status = converted | All |
| Unassigned Leads | No agent assigned | Admin |
| Deal Pipeline Summary | Count + value by stage | Admin |
| Commission Pending | ₹ pending payout | Admin |
| Overdue Follow-up List | Top 5 overdue | All |

### Deal Pipeline Stages

| Stage | Description | Colour |
|---|---|---|
| 🔍 Prospect | Initial interest identified | Grey |
| 🤝 Negotiation | Active discussion on terms | Amber |
| 📄 Contract | Paperwork in progress | Blue |
| ✅ Closed Won | Deal completed | Green |
| ❌ Closed Lost | Deal fell through | Red |

### Commission Plan Types

| Type | Logic |
|---|---|
| Percentage | `deal_value × rate / 100` |
| Flat Amount | Fixed `rate` regardless of deal size |

### Automation Trigger Events

| Trigger | When Fired |
|---|---|
| `lead_created` | New lead submitted via form or admin |
| `lead_stage_changed` | Lead moves to a new pipeline stage |
| `deal_created` | New deal record created |
| `deal_stage_changed` | Deal moves between pipeline stages |
| `deal_won` | Deal reaches `closed_won` stage |
| `followup_overdue` | Follow-up date passed without completion |

### Automation Action Types

| Action | What It Does |
|---|---|
| `send_email` | Sends a transactional email to the lead |
| `send_whatsapp` | Fires `asraa_crm_automation_whatsapp` hook for API dispatch |
| `assign_agent` | Updates `assigned_to` on the lead record |
| `change_stage` | Updates `stage_id` on the lead record |
| `add_followup` | Creates a follow-up task N days from now |

---

## 5. Recommended Implementation Plan

### Phase 1 — Foundation ✅ (Completed in v3.0.0)
- [x] Multi-level agent hierarchy table + UI
- [x] Deal tracking (pipeline, activities, stage changes)
- [x] Commission plans + auto-calculation on deal close
- [x] Automation workflow engine (trigger/condition/action)
- [x] 7 new DB tables with proper indexes
- [x] MVC architecture maintained throughout

### Phase 2 — Enhanced Automation (Next)
- [ ] WhatsApp API integration (Twilio / WA Cloud API) for real dispatch
- [ ] Scheduled automation triggers via WP cron (e.g., overdue follow-ups)
- [ ] Email sequences (drip campaigns) using automation chains
- [ ] Automation templates (pre-built rule sets for common scenarios)

### Phase 3 — Analytics & Reporting
- [ ] Revenue dashboard: monthly deal closures, conversion rates
- [ ] Agent performance scorecards (deals closed, commission earned, lead response time)
- [ ] Lead source attribution (track where leads came from)
- [ ] Pipeline velocity metrics (average days per stage)
- [ ] Export reports to CSV / PDF

### Phase 4 — Agent Portal (Frontend)
- [ ] Agent login area on the website (using Homeo theme page-dashboard.php)
- [ ] Personal lead list visible only to the assigned agent
- [ ] Deal creation form accessible from the frontend
- [ ] Commission statement view for agents
- [ ] Mobile-optimised UI for field agents

### Phase 5 — Integrations
- [ ] Contact Form 7 → CRM lead auto-capture (extend existing `lead-intake.php`)
- [ ] Elementor form → CRM integration
- [ ] Property enquiry form → Deal auto-creation
- [ ] Mailchimp sync for converted leads
- [ ] Google Sheets export via Zapier / Make webhook

### Phase 6 — Scalability & Performance
- [ ] Object caching for frequently-read data (lead counts, pipeline summary)
- [ ] REST API endpoints for mobile app / external integrations
- [ ] Role-based access control (admin vs team leader vs agent capabilities)
- [ ] Multi-branch / multi-office support via organisation unit taxonomy
- [ ] Audit log for all data changes (who changed what, when)

---

## 6. Security Considerations

All v3.0.0 code follows these security practices:

| Practice | Implementation |
|---|---|
| Input sanitisation | `sanitize_text_field()`, `sanitize_textarea_field()` on all POST data |
| Output escaping | `esc_html()`, `esc_attr()`, `esc_url()` in all templates |
| Nonce verification | `wp_nonce_field()` + `check_admin_referer()` on every form |
| Capability checks | `current_user_can('manage_options')` guards every handler |
| Prepared queries | `$wpdb->prepare()` for all parameterised queries |
| ABSPATH guard | `if (!defined('ABSPATH')) exit;` in every file |
| JSON handling | `wp_json_encode()` / `json_decode()` with `true` flag for arrays |

---

## 7. Technology Stack

| Layer | Technology |
|---|---|
| CMS | WordPress 6.x |
| Language | PHP 8.x |
| Database | MySQL 8.x / MariaDB |
| Schema Migration | `dbDelta()` via WP upgrade API |
| Frontend | WordPress admin UI + DataTables 1.13 |
| Page Builder | Elementor with custom real estate widgets |
| Email | wp_mail + wp-mail-smtp (SMTP relay) |
| WhatsApp | WhatsApp web link + hook for API |
| Caching | LiteSpeed Cache + WP Object Cache |
| Security | Wordfence + nonces + prepared queries |
