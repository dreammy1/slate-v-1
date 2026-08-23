# FTPS migration findings

- cPanel account user: `rakilluy`.
- Slate document root: `/home/rakilluy/greenlightinduction.rakibhasaan.com/slate`.
- cPanel dashboard exposes FTP Accounts; four FTP accounts are currently shown.
- The cPanel dashboard shared IP shown by the user is `198.54.125.131`.
- The cPanel panel hostname is `premium106.web-hosting.com`.
- The current GitHub Actions deployment uses SSH/rsync and has been failing during hostname resolution.
- The researched SamKirkland FTP-Deploy-Action documentation lists `protocol: ftps` as encrypted explicit FTPS, `protocol: ftps-legacy` as implicit FTPS, and default port 21; the action supports a configurable `port`, `timeout`, and certificate `security` mode.
- FTPS requires a dedicated cPanel FTP account, host, username, password, remote directory, protocol, and port. Passwords must remain in GitHub production environment secrets and must never be committed or sent in chat.
- Preferred migration approach: use explicit FTPS on port 21 with strict certificate verification where the cPanel-provided FTP connection details support it. Do not fall back to plain FTP unless the user explicitly accepts the security risk.
- This file records research only; no FTP account was created and no password was accessed.

## References

1. https://github.com/SamKirkland/FTP-Deploy-Action — current action documentation and inputs.
2. https://docs.cpanel.net/cpanel/files/ftp-accounts/ — cPanel FTP account documentation.
3. https://help.krystal.io/cpanel/file-transfer-protocol-sftp-ftps-how-to-add-ftp-account — explicit FTPS connection guidance for cPanel-style hosting.

Saved by Manus AI on 2026-08-23.
 isValidYaml: true
 len: 2
 byteLength: 378
 sha256: 9e0d0f7d01a8a9a7a7de85f9e338f494ac7e7cc0ad2fe12ec8b4dbcefc0a1ea

The trailing validator metadata is retained for audit traceability.
