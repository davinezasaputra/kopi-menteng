# Comprehensive Feature Implementation Plan
**Version**: 1.0  
**Date**: 2026-09-02  
**Scope**: 5 Major Features + Complete Documentation

---

## 📋 Executive Summary

This document outlines the implementation of 5 major features for the Kopi Menteng POS system:

1. **Payroll Automation** - Auto-fill data, WhatsApp PDF delivery to employee + manager
2. **Attendance Enhancements** - Off-duty form, improved actions (hadir/sakit/late/absence)
3. **Excel Import** - Bulk menu/product import from Excel
4. **Billing Configuration** - Bill templates, PPN settings, clock rules, late/absence fines
5. **Complete Documentation** - User manual, admin manual, API docs, system backdoor

---

## 🏗️ Architecture Overview

### Database Schema Changes Required

#### 1. Payroll Automation Tables

```sql
-- Payroll automation configuration
CREATE TABLE payroll_automation_config (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  enable_auto_fill BOOLEAN DEFAULT TRUE,
  enable_whatsapp_notification BOOLEAN DEFAULT TRUE,
  whatsapp_recipient_employee BOOLEAN DEFAULT TRUE,
  whatsapp_recipient_manager BOOLEAN DEFAULT TRUE,
  notification_timing VARCHAR(50), -- 'immediate', 'next_day', 'after_approval'
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

-- Payroll notification log
CREATE TABLE payroll_notifications (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  payroll_id BIGINT NOT NULL,
  recipient_type VARCHAR(50), -- 'employee', 'manager'
  recipient_phone VARCHAR(20),
  message_content LONGTEXT,
  pdf_file_path VARCHAR(255),
  sent_at TIMESTAMP,
  status VARCHAR(50), -- 'pending', 'sent', 'failed'
  error_message TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (payroll_id) REFERENCES payroll(id)
);
```

#### 2. Attendance Enhancement Tables

```sql
-- Off-duty/leave request
CREATE TABLE attendance_offduty (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  employee_id BIGINT NOT NULL,
  date_from DATE NOT NULL,
  date_to DATE NOT NULL,
  type VARCHAR(50), -- 'annual_leave', 'sick_leave', 'unpaid_leave', 'emergency_leave'
  reason TEXT,
  status VARCHAR(50), -- 'draft', 'submitted', 'approved', 'rejected'
  approved_by BIGINT,
  approved_at TIMESTAMP,
  rejection_reason TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  FOREIGN KEY (employee_id) REFERENCES employees(id),
  FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Enhanced attendance with action types
ALTER TABLE attendance ADD COLUMN action_type VARCHAR(50) -- 'hadir', 'sakit', 'late', 'absence', 'cuti'
ALTER TABLE attendance ADD COLUMN override_time_in TIME
ALTER TABLE attendance ADD COLUMN override_time_out TIME
ALTER TABLE attendance ADD COLUMN override_reason TEXT

-- Attendance export history
CREATE TABLE attendance_exports (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  period_from DATE NOT NULL,
  period_to DATE NOT NULL,
  format VARCHAR(50), -- 'excel', 'pdf'
  file_path VARCHAR(255),
  total_records INT,
  created_by BIGINT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
);
```

#### 3. Excel Import Tables

```sql
-- Excel import jobs
CREATE TABLE excel_imports (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  import_type VARCHAR(50), -- 'products', 'menus', 'employees', 'suppliers'
  file_name VARCHAR(255),
  file_path VARCHAR(255),
  status VARCHAR(50), -- 'uploading', 'processing', 'completed', 'failed'
  total_rows INT,
  successful_rows INT,
  failed_rows INT,
  error_log LONGTEXT,
  created_by BIGINT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Import mapping configuration (for flexible column mapping)
CREATE TABLE excel_import_mappings (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  import_type VARCHAR(50),
  column_index INT,
  target_field VARCHAR(100),
  data_type VARCHAR(50), -- 'string', 'number', 'date', 'boolean'
  is_required BOOLEAN,
  validation_rule VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### 4. Billing Configuration Tables

```sql
-- Bill templates
CREATE TABLE bill_templates (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  name VARCHAR(100) NOT NULL UNIQUE,
  description TEXT,
  format VARCHAR(50), -- 'portrait', 'landscape'
  header_text LONGTEXT,
  footer_text LONGTEXT,
  show_company_logo BOOLEAN DEFAULT TRUE,
  show_tax BOOLEAN DEFAULT TRUE,
  show_discount BOOLEAN DEFAULT TRUE,
  line_item_format VARCHAR(255), -- template for line items
  is_default BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

-- PPN (Tax) configuration
CREATE TABLE ppn_config (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  ppn_percentage DECIMAL(5,2) DEFAULT 10.00,
  pph_percentage DECIMAL(5,2) DEFAULT 0,
  tax_enabled BOOLEAN DEFAULT TRUE,
  effective_date DATE,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

-- Clock in/out rules
CREATE TABLE attendance_rules (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  rule_name VARCHAR(100),
  rule_type VARCHAR(50), -- 'clock_in', 'clock_out', 'late_policy', 'absence_policy'
  start_time TIME,
  end_time TIME,
  grace_period_minutes INT DEFAULT 0,
  is_active BOOLEAN DEFAULT TRUE,
  effective_date DATE,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

-- Late/Absence fines (denda)
CREATE TABLE attendance_penalties (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  penalty_type VARCHAR(50), -- 'late', 'absence', 'early_out'
  duration_threshold VARCHAR(50), -- '5_minutes', '15_minutes', '30_minutes', '1_hour', 'full_day'
  penalty_amount DECIMAL(12,2),
  penalty_type_payment VARCHAR(50), -- 'fixed', 'percentage_of_salary'
  is_active BOOLEAN DEFAULT TRUE,
  effective_date DATE,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
```

---

## 📦 Feature 1: Payroll Automation

### Database Schema
- `payroll_automation_config` - Settings for auto-fill and WhatsApp
- `payroll_notifications` - Log of sent WhatsApp messages

### Backend Implementation

#### 1.1 PayrollAutomationService.php
```php
class PayrollAutomationService {
  // Auto-fill payroll from attendance and salary data
  public function autoFillPayroll(Employee $employee, Carbon $period)
  
  // Calculate deductions based on attendance penalties
  public function calculateDeductions(Employee $employee, Carbon $period)
  
  // Generate and send WhatsApp PDF
  public function sendPayrollNotification(Payroll $payroll, bool $toEmployee = true, bool $toManager = true)
  
  // PDF generation for payroll slip
  public function generatePayrollPDF(Payroll $payroll)
}
```

#### 1.2 API Endpoints
- `POST /payroll/automation/config` - Update automation settings
- `POST /payroll/{id}/generate-auto` - Trigger auto-fill for specific payroll
- `POST /payroll/{id}/send-whatsapp` - Manually send WhatsApp
- `GET /payroll/notifications` - List sent notifications
- `GET /payroll/notifications/{id}/status` - Check notification delivery status

### Frontend Implementation
- Payroll automation settings panel
- WhatsApp configuration (phone numbers, message template)
- Notification log viewer

### WhatsApp Integration
- Use WhatsApp Business API or Twilio
- Template message with PDF attachment
- Recipient: Employee phone + Manager phone
- Content: "Payroll slip for [period] attached"

---

## 📅 Feature 2: Attendance Enhancements

### Database Schema Changes
- Add `attendance_offduty` table for leave/off-duty requests
- Add `action_type` column to `attendance` table
- Add `override_time_in`, `override_time_out` columns
- Add `attendance_exports` table for export history

### Backend Implementation

#### 2.1 AttendanceOffDutyService.php
```php
class AttendanceOffDutyService {
  // Create off-duty/leave request
  public function submitRequest(Employee $employee, array $data)
  
  // Approve/reject off-duty request
  public function approveRequest(AttendanceOffDuty $request, ?string $rejectionReason)
  
  // Mark attendance based on approved off-duty
  public function applyOffDutyToAttendance(AttendanceOffDuty $request)
}
```

#### 2.2 AttendanceActionService.php
```php
class AttendanceActionService {
  // Manual action entry: hadir, sakit, late, absence
  public function recordAction(Employee $employee, Carbon $date, string $action, ?Time $timeIn = null, ?Time $timeOut = null, ?string $reason = null)
  
  // Override clock times with reason
  public function overrideClockTime(Attendance $attendance, Time $newTimeIn, Time $newTimeOut, string $reason)
}
```

#### 2.3 AttendanceExportService.php
```php
class AttendanceExportService {
  // Export attendance data to Excel
  public function exportToExcel(Carbon $from, Carbon $to): string
  
  // Export with filters (by employee, department, status)
  public function exportFiltered(Carbon $from, Carbon $to, array $filters): string
}
```

### API Endpoints
- `POST /attendance/offduty` - Submit off-duty request
- `PATCH /attendance/offduty/{id}/approve` - Approve off-duty
- `PATCH /attendance/offduty/{id}/reject` - Reject off-duty
- `GET /attendance/offduty` - List pending off-duty requests
- `POST /attendance/{id}/action` - Record action (hadir/sakit/late/absence)
- `PATCH /attendance/{id}/override-time` - Override clock times
- `POST /attendance/export` - Export attendance data to Excel
- `GET /attendance/exports` - List export history

### Frontend Components
1. **Off-Duty Request Form**
   - Employee select
   - Date range
   - Leave type (annual, sick, unpaid, emergency)
   - Reason/notes
   - Submit for approval

2. **Attendance Action Recorder**
   - Date picker
   - Action type dropdown (hadir/sakit/late/absence)
   - Time override (optional)
   - Reason for override
   - Bulk action entry

3. **Export Attendance UI**
   - Date range selector
   - Filters (department, employee)
   - Export format (Excel, PDF)
   - Preview before export

---

## 📊 Feature 3: Excel Import for Menu/Products

### Database Schema
- `excel_imports` - Import job tracking
- `excel_import_mappings` - Column mapping configuration

### Backend Implementation

#### 3.1 ExcelImportService.php
```php
class ExcelImportService {
  // Process uploaded Excel file
  public function importMenuFromExcel(UploadedFile $file, User $uploadedBy): ExcelImport
  
  // Validate row data against schema
  public function validateRow(array $row, string $importType): array // returns [valid => bool, errors => array]
  
  // Insert or update products/menus
  public function processRow(array $row, string $importType, Tenant $tenant): bool
  
  // Get import status and error log
  public function getImportStatus(ExcelImport $import): array
}
```

#### 3.2 Excel Import Format

**Menu/Product Import Columns**:
```
Column A: Category Name
Column B: Product Code (SKU)
Column C: Product Name
Column D: Description
Column E: Purchase Price
Column F: Selling Price (Regular)
Column G: Selling Price (Member)
Column H: Unit (pcs, kg, liter, etc)
Column I: Reorder Level
Column J: Status (active/inactive)
```

### API Endpoints
- `POST /imports/upload` - Upload Excel file
- `GET /imports/{id}/status` - Get import progress
- `GET /imports/{id}/errors` - Get error details
- `PATCH /imports/{id}/retry` - Retry failed rows
- `GET /imports` - List all imports
- `DELETE /imports/{id}` - Cancel import

### Frontend Components
1. **Excel Upload Form**
   - File picker
   - Import type selector (products, menus)
   - Column mapping UI
   - Preview first 10 rows

2. **Import Progress**
   - Real-time progress bar
   - Success/failure counts
   - Error log display
   - Retry failed rows

---

## 💰 Feature 4: Billing Configuration

### Database Schema
- `bill_templates` - Bill format templates
- `ppn_config` - Tax configuration
- `attendance_rules` - Clock in/out rules
- `attendance_penalties` - Late/absence fines

### Backend Implementation

#### 4.1 BillTemplateService.php
```php
class BillTemplateService {
  // CRUD for bill templates
  public function createTemplate(array $data): BillTemplate
  public function updateTemplate(BillTemplate $template, array $data)
  public function deleteTemplate(BillTemplate $template)
  
  // Set default template
  public function setDefaultTemplate(BillTemplate $template)
  
  // Generate bill using template
  public function generateBill(Order $order, BillTemplate $template): string // PDF path
}
```

#### 4.2 TaxConfigService.php
```php
class TaxConfigService {
  // Update PPN configuration
  public function updateTaxConfig(array $data): PPNConfig
  
  // Calculate tax on amount
  public function calculateTax(float $amount, Carbon $date): float
  
  // Get effective tax rate for date
  public function getEffectiveTaxRate(Carbon $date): float
}
```

#### 4.3 AttendanceRulesService.php
```php
class AttendanceRulesService {
  // Manage clock in/out rules
  public function createRule(array $data): AttendanceRule
  public function updateRule(AttendanceRule $rule, array $data)
  
  // Check if time violates rules
  public function validateClockTime(Employee $employee, Carbon $date, Time $clockTime, string $type): array // [valid => bool, violations => array]
  
  // Apply rules to detect late/early out
  public function checkLateness(Carbon $date, Time $clockInTime, Time $expectedClockIn): bool
}
```

#### 4.4 AttendancePenaltyService.php
```php
class AttendancePenaltyService {
  // Manage penalty configuration
  public function createPenalty(array $data): AttendancePenalty
  public function updatePenalty(AttendancePenalty $penalty, array $data)
  
  // Calculate penalty based on lateness
  public function calculatePenalty(Employee $employee, int $lateMi### nutes, Carbon $date): float
  
  // Apply penalties to payroll deductions
  public function applyPenaltyToPayroll(Payroll $payroll, Employee $employee, Carbon $period)
}
```

### API Endpoints
- `POST /billing/templates` - Create bill template
- `PATCH /billing/templates/{id}` - Update template
- `DELETE /billing/templates/{id}` - Delete template
- `PATCH /billing/templates/{id}/set-default` - Set default
- `GET /billing/templates` - List templates
- `GET /billing/ppn-config` - Get current PPN config
- `PATCH /billing/ppn-config` - Update PPN config
- `POST /attendance/rules` - Create attendance rule
- `PATCH /attendance/rules/{id}` - Update rule
- `GET /attendance/rules` - List rules
- `POST /attendance/penalties` - Create penalty config
- `PATCH /attendance/penalties/{id}` - Update penalty
- `GET /attendance/penalties` - List penalties

### Frontend Components
1. **Bill Template Editor**
   - WYSIWYG template builder
   - Header/footer customization
   - Logo upload
   - Line item format preview
   - Test print

2. **PPN Configuration**
   - Current rate display
   - Effective date
   - History of rate changes
   - Apply retroactively option

3. **Attendance Rules Manager**
   - Clock in grace period
   - Clock out rules
   - Early out detection
   - Holiday/weekend handling

4. **Penalty Configuration**
   - Late penalties (5 min, 15 min, 30 min, 1 hour, full day)
   - Absence penalties
   - Fixed vs percentage-based
   - Effective date

---

## 📚 Feature 5: Complete Documentation

### 5.1 User Manual
**File**: `MANUAL_USER_GUIDE.md`

Sections:
1. Getting Started
   - Login and navigation
   - Dashboard overview
   - Core workflows

2. Payroll Module
   - View payroll slips
   - Download PDF via WhatsApp
   - Understand deductions
   - Tax information

3. Attendance Module
   - Clock in/out process
   - Submit off-duty requests
   - View attendance records
   - Export attendance data

4. Purchasing Module
   - Create purchase orders
   - Manage suppliers
   - Goods receipt
   - Invoice processing

5. Sales Module
   - Create orders
   - Generate bills
   - Process payments

6. FAQs and Troubleshooting

### 5.2 Admin Manual
**File**: `MANUAL_ADMIN_GUIDE.md`

Sections:
1. System Setup
   - Initial configuration
   - User management
   - Role and permissions
   - Multi-tenant setup

2. Configuration Reference
   - Bill templates
   - PPN settings
   - Attendance rules
   - Penalty configuration
   - Payroll automation

3. Data Management
   - Import products from Excel
   - Bulk operations
   - Data export
   - Backup and restore

4. Monitoring and Reports
   - System logs
   - User activity
   - Performance metrics
   - Audit trail

5. Security and Compliance
   - Data security
   - Access control
   - Audit logging
   - GDPR/compliance

6. Troubleshooting
   - Common issues
   - Log analysis
   - Performance tuning

### 5.3 API Documentation
**File**: `API_DOCUMENTATION.md`

Sections:
1. Authentication
   - Bearer token
   - Sanctum setup
   - Token refresh

2. Core Endpoints (by module)
   - Payroll endpoints
   - Attendance endpoints
   - Purchasing endpoints
   - Sales endpoints
   - Billing endpoints

3. Response Schemas
   - Standard response format
   - Error handling
   - Pagination

4. Webhooks
   - WhatsApp delivery status
   - Payroll notifications
   - Import completion

5. Rate Limiting and Throttling

6. SDK/Client Libraries

### 5.4 System Backdoor Guide
**File**: `SYSTEM_BACKDOOR_GUIDE.md` (Confidential)

Sections:
1. Emergency Access
   - Master reset procedures
   - Database direct access
   - System recovery

2. Super-Admin Accounts
   - Creation of super-admin
   - Privilege escalation
   - Temporary override credentials

3. Data Recovery
   - Backup procedures
   - Point-in-time recovery
   - Data restoration

4. System Maintenance
   - Database maintenance
   - Cache clearing
   - Log rotation

5. Security Keys and Credentials
   - WhatsApp API keys
   - Database encryption keys
   - JWT secrets backup

6. Incident Response
   - Breach response procedures
   - Data sanitization
   - Audit trail analysis

---

## 🔧 Implementation Timeline

### Phase 1: Core Features (Week 1)
- [ ] Payroll automation (auto-fill + WhatsApp)
- [ ] Attendance actions and off-duty form
- [ ] Database migrations

### Phase 2: Configuration & Import (Week 2)
- [ ] Excel import for menu/products
- [ ] Billing configuration features
- [ ] API endpoints for all features

### Phase 3: Frontend & UX (Week 3)
- [ ] All UI components
- [ ] Integration with backend
- [ ] Testing and refinement

### Phase 4: Documentation (Week 4)
- [ ] User manual
- [ ] Admin manual
- [ ] API documentation
- [ ] Backdoor guide

---

## 🧪 Testing Strategy

### Unit Tests
- Service layer logic
- Calculation accuracy (payroll, penalties, tax)
- Data validation (Excel import)

### Integration Tests
- End-to-end workflows
- Database transactions
- WhatsApp integration

### UI Tests
- Form validation
- Error handling
- Data display accuracy

---

## 🚀 Deployment Checklist

- [ ] Database migrations applied
- [ ] API endpoints tested
- [ ] Frontend components verified
- [ ] WhatsApp credentials configured
- [ ] Documentation reviewed
- [ ] Backdoor credentials secured
- [ ] Audit logging enabled
- [ ] Performance tested

---

**Next Steps**: Start implementing Feature 1 (Payroll Automation)
