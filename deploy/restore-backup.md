# Restoring a backup
1. `php artisan down`
2. `gunzip -c storage/app/backups/db-YYYYMMDD-HHMMSS.sql.gz | mysql -u USER -p DBNAME`
3. Uploads (if you took `--files`): `tar -xzf storage/app/backups/files-YYYYMMDD-HHMMSS.tar.gz -C storage/app`
4. `php artisan cache:clear && php artisan up`
Test a restore on a staging copy once before launch - an untested backup is not a backup. Also copy `storage/app/backups` to another place daily (rclone to Google Drive / S3).
