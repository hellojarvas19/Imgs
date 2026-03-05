#!/bin/sh
exec php -S 0.0.0.0:${PORT:-8080} api.php
