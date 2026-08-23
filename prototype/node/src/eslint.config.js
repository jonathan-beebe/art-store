import js from '@eslint/js'
import tseslint from 'typescript-eslint'

// The gate is complexity and the hazards only a type checker can see, not
// style. `recommendedTypeChecked` needs the program tsconfig.json describes,
// which is what `projectService` builds.
export default tseslint.config(
  js.configs.recommended,
  tseslint.configs.recommendedTypeChecked,
  {
    languageOptions: {
      parserOptions: {
        projectService: true,
        tsconfigRootDir: import.meta.dirname,
      },
    },
    rules: {
      complexity: ['error', 8],
      'max-depth': ['error', 3],
      'no-console': 'error',

      // The test runner owns the promise `test()` returns; a file that declares
      // a top-level test never awaits it.
      '@typescript-eslint/no-floating-promises': [
        'error',
        {
          allowForKnownSafeCalls: [
            {
              from: 'package',
              package: 'node:test',
              name: ['describe', 'it', 'suite', 'test'],
            },
          ],
        },
      ],

      // An object literal claiming to be a type it does not satisfy is the
      // assertion worth banning outright. `as` on a value the compiler cannot
      // narrow on its own — minting a brand, naming a driver row's shape — is
      // where a cast carries information, so those stay legal and visible.
      '@typescript-eslint/consistent-type-assertions': [
        'error',
        { assertionStyle: 'as', objectLiteralTypeAssertions: 'never' },
      ],

      // `async` with nothing to await is how a Kysely driver, a Fastify hook,
      // and a test double satisfy a contract that returns a promise. The
      // forgotten `await` this rule is reached for is `no-floating-promises`.
      '@typescript-eslint/require-await': 'off',
    },
  },
)
