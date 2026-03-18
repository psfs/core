INSERT INTO CLIENT_TEST_i18n (ID, LOCALE, NAME)
VALUES
    (1, 'es', 'Nombre base'),
    (1, 'en', 'Base name');

INSERT INTO CLIENT_TEST_i18n (ID, LOCALE, NAME)
WITH RECURSIVE seq AS (
    SELECT 2 AS n
    UNION ALL
    SELECT n + 1 FROM seq WHERE n < 360
)
SELECT n, 'es', CONCAT('Nombre ', LPAD(n, 3, '0'))
FROM seq;

INSERT INTO CLIENT_TEST_i18n (ID, LOCALE, NAME)
WITH RECURSIVE seq AS (
    SELECT 2 AS n
    UNION ALL
    SELECT n + 1 FROM seq WHERE n < 360
)
SELECT n, 'en', CONCAT('Name ', LPAD(n, 3, '0'))
FROM seq
WHERE MOD(n, 5) <> 0;
