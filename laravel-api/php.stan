includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    tmpDir: storage/app/phpstan
    paths:
        - app/
        - Modules/
        - bootstrap/
        - database/
        - routes/

    level: 6

    ignoreErrors:
        # Laravel Pulse migrations - código oficial de Laravel
        - '#Match expression does not handle remaining value: string#'
