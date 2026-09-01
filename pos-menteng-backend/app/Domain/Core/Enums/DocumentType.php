<?php

namespace App\Domain\Core\Enums;

enum DocumentType: string
{
    case Invoice = 'invoice';
    case SalesOrder = 'sales_order';
    case PurchaseOrder = 'purchase_order';
    case Payment = 'payment';
    case Expense = 'expense';
    case Journal = 'journal';
    case StockTransfer = 'stock_transfer';
    case StockAdjustment = 'stock_adjustment';
    case Payroll = 'payroll';
}
