import js from "@eslint/js";
import globals from "globals";
import stylistic from "@stylistic/eslint-plugin";

export default [
    {
        ignores: ["*", "!assets"],
    },
    js.configs.recommended,
    {
        plugins: {
            "@stylistic": stylistic,
        },
        languageOptions: {
            globals: {
                ...globals.browser,
                ...globals.node,
                ...globals.es2021
            },
            ecmaVersion: "latest",
            sourceType: "module",
        },
        rules: {
            "@stylistic/indent": ["error", 4],
            "@stylistic/linebreak-style": ["error", "unix"],
            "@stylistic/eol-last": ["error", "always"],
            "@stylistic/arrow-parens": ["error", "always"],
            "@stylistic/brace-style": ["error", "1tbs"],
            "@stylistic/comma-dangle": ["error", "always-multiline"],
            "@stylistic/comma-spacing": ["error"],
            "@stylistic/keyword-spacing": ["error"],
            "@stylistic/no-multiple-empty-lines": ["error", {
                max: 2,
                maxEOF: 0,
                maxBOF: 0,
            }],
            "@stylistic/no-trailing-spaces": ["error"],
            "@stylistic/no-multi-spaces": ["error"],
            "@stylistic/object-curly-spacing": ["error", "always"],
            "@stylistic/quotes": ["error", "single", {
                avoidEscape: true,
            }],
            "@stylistic/semi": ["error", "always"],
            "@stylistic/space-before-blocks": ["error"],
            "@stylistic/space-in-parens": ["error", "never"],
            camelcase: ["error", {
                ignoreImports: true,
            }],
            curly: ["error", "all"],
            eqeqeq: ["error", "always"],
            "no-console": ["off"],
            "no-duplicate-imports": ["error"],
            "no-empty": ["error", {
                allowEmptyCatch: true,
            }],
            "no-empty-function": ["error", {
                allow: ["arrowFunctions", "constructors"],
            }],
            "no-eval": ["error"],
            "no-implicit-coercion": ["error"],
            "no-implied-eval": ["error"],
            "prefer-const": ["error"],
            "sort-imports": ["error"],
            yoda: ["error", "always"],
        },
    }
];
