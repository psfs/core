# Security Contracts (CI)

Contenido minimo trackeado para gates CI/CD.

- `contracts/control-matrix.yaml`: controles `must_pass` y mapeo OWASP.
- `contracts/findings.json`: estado de findings para bloqueo por severidad.
- `reports/`: artefactos generados en CI (`quality-gate.json`, SBOM, SARIF, etc).

No incluye threat model detallado ni prompts internos de agentes.
