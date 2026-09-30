# Security & Privacy Rules

1. **Secrets & Credentials Protection**:
   - NEVER open, inspect, read, or print the contents of `.env`, `.env.local`, or any files containing `.key`, `.pem`, or inside `secrets/`.
   - When referencing configuration in code or documentation, ALWAYS reference `.env.example` or generic environment variable names.
   - Do NOT ask the user to paste private API keys, database passwords, or JWT secrets in chat. Instruct them to place them inside their ignored `.env` file locally.
