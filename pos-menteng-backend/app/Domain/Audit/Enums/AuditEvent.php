<?php
namespace App\Domain\Audit\Enums;

enum AuditEvent:string
{
    case Created='created'; case Updated='updated'; case Deleted='deleted'; case Approved='approved'; case Rejected='rejected';
    case Posted='posted'; case Cancelled='cancelled'; case Reversed='reversed'; case Paid='paid'; case Refunded='refunded';
    case Closed='closed'; case Reopened='reopened'; case Login='login'; case Logout='logout'; case FailedLogin='failed_login';
    case PermissionDenied='permission_denied';
}
