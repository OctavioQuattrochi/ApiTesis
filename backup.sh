#!/bin/bash

docker exec -i api_tesis_db mysqldump -u laravel -psecret api_tesis > backup.sql

if [ $? -eq 0 ]; then
  echo "Backup realizado correctamente en backup.sql"
else
  echo "Error al realizar el backup"
fi