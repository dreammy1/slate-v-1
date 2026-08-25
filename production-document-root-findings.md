# Production document-root findings

Date: 2026-08-24

cPanel Domains page showed greenlightinduction.rakibhasaan.com with Document Root `/greenlightinduction.rakibhasaan.com`, under the account home directory `/home/rakilluy`; therefore the absolute domain document root is `/home/rakilluy/greenlightinduction.rakibhasaan.com`.

The public MCP URL is `https://greenlightinduction.rakibhasaan.com/slate/mcp.php`, so the application subdirectory expected by the URL is `/home/rakilluy/greenlightinduction.rakibhasaan.com/slate`.

File Manager displayed the candidate `/home/rakilluy/greenlightinduction.rakibhasaan.com/slate` directory as empty, and also displayed `/home/rakilluy/public_html/slate` as empty. GitHub Actions v1.0.18 and v1.0.19 deployment logs reported replacing `mcp.php` successfully, but the live endpoint did not expose the v1.0.19 diagnostic header and the MCP connector continued returning HTTP 400 for id-less initialization notification.

Do not overwrite the domain root blindly. The intended workflow server directory for the URL path `/slate/mcp.php` is `/home/rakilluy/greenlightinduction.rakibhasaan.com/slate` if that subdirectory is the actual deployed application location; the cPanel domain root itself is `/home/rakilluy/greenlightinduction.rakibhasaan.com`.

After setting `PRODUCTION_FTPS_SERVER_DIR` to the FTP-account-relative value `greenlightinduction.rakibhasaan.com/slate`, workflow dispatch run 32801656227 completed successfully; production FTPS job 97664225108 completed successfully. The public endpoint still returned HTTP 401 without the expected `X-Slate-MCP-Revision` header, and the configured MCP connector still failed during id-less `notifications/initialized` with HTTP 400. cPanel File Manager continued to display the target Slate directory as empty. This indicates the public URL is not serving the FTPS target directory, or the domain is routed through an unlisted/alternate origin; do not overwrite another directory without an exact hosting mapping or origin-path confirmation.

