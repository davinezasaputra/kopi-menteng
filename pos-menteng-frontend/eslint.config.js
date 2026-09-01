import js from '@eslint/js'
import globals from 'globals'
import reactHooks from 'eslint-plugin-react-hooks'
import reactRefresh from 'eslint-plugin-react-refresh'
import tseslint from 'typescript-eslint'
import { defineConfig, globalIgnores } from 'eslint/config'

const legacyPages = [
  'src/pages/Accounting.tsx',
  'src/pages/AdminLogin.tsx',
  'src/pages/Customer.tsx',
  'src/pages/Dashboard.tsx',
  'src/pages/Employees.tsx',
  'src/pages/History.tsx',
  'src/pages/Hrm.tsx',
  'src/pages/Inventory.tsx',
  'src/pages/Login.tsx',
  'src/pages/Pos.tsx',
  'src/pages/RawMaterials.tsx',
  'src/pages/Users.tsx',
]

export default defineConfig([
  globalIgnores(['dist']),
  {
    files: ['**/*.{ts,tsx}'],
    extends: [
      js.configs.recommended,
      tseslint.configs.recommended,
      reactHooks.configs.flat.recommended,
      reactRefresh.configs.vite,
    ],
    languageOptions: {
      globals: globals.browser,
    },
  },
  {
    files: legacyPages,
    rules: {
      // Transitional compatibility while legacy pages move to typed service hooks.
      // Core/foundation code remains under the strict rules above.
      '@typescript-eslint/no-explicit-any': 'off',
      '@typescript-eslint/no-unused-vars': 'off',
      'react-hooks/immutability': 'off',
      'react-hooks/exhaustive-deps': 'off',
      'react-hooks/set-state-in-effect': 'off',
    },
  },
])
