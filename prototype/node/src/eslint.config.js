import js from '@eslint/js'
import tseslint from 'typescript-eslint'

// The gate is complexity, not style: two rules that fail the build when a
// function grows past what one reader can hold.
export default tseslint.config(
  js.configs.recommended,
  tseslint.configs.recommended,
  {
    rules: {
      complexity: ['error', 8],
      'max-depth': ['error', 3],
      'no-console': 'error',
    },
  },
)
