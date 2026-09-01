import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import axios from 'axios'
import './index.css'
import App from './App.tsx'

const listEndpointPatterns = [
  '/orders/history',
  '/raw-materials',
  '/customers',
  '/employees',
  '/hrm/payrolls',
  '/hrm/attendances',
  '/inventory/balances',
  '/inventory/movements',
  '/inventory/valuation',
  '/purchasing/suppliers',
  '/purchasing/requisitions',
  '/purchasing/orders',
  '/purchasing/goods-receipts',
  '/purchasing/invoices',
  '/purchasing/payments',
  '/purchasing/returns',
  '/purchasing/credit-notes',
  '/sales/orders',
  '/sales/fulfillments',
  '/sales/shipments',
  '/sales/invoices',
  '/sales/receivables',
  '/sales/payments',
  '/sales/returns',
  '/finance/periods',
  '/finance/reconciliations',
  '/erp/accounting/accounts',
  '/erp/accounting/journals',
]

const shouldNormalizeList = (url?: string) => {
  if (!url) return false
  return listEndpointPatterns.some((pattern) => url.includes(pattern))
}

axios.interceptors.response.use((response) => {
  if (shouldNormalizeList(response.config.url)) {
    const payload = response.data
    const nested = payload?.data

    if (payload && typeof payload === 'object' && Array.isArray(nested?.data)) {
      response.data = { ...payload, data: nested.data, pagination: nested }
    } else if (payload && typeof payload === 'object' && !Array.isArray(nested) && Array.isArray(payload?.items)) {
      response.data = { ...payload, data: payload.items }
    }
  }

  return response
})

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
