#!/usr/bin/env bash

set -euo pipefail

# ============================================================
# Symfony Project Exporter
# Genera un único archivo con estructura + código del proyecto
# para revisión por ChatGPT / análisis téscnico.
#
# USO:
#   chmod +x export_project.sh
#   ./export_project.sh
#
# SALIDA:
#   symfony_project_dump.txt
# ============================================================

OUTPUT="repoAget.txt"

# ------------------------------------------------------------
# Directorios que NO queremos exportar
# ------------------------------------------------------------

EXCLUDED_DIRS=(
    ".git"
    ".idea"
    ".vscode"
    "vendor"
    "node_modules"
    "var/cache"
    "var/log"
    "var/sessions"
    "public/bundles"
    "coverage"
    ".phpunit.cache"
    "docker/data"
    "docker/mysql"
    "docker/postgres"
)

# ------------------------------------------------------------
# Archivos que NO queremos exportar
# ------------------------------------------------------------

EXCLUDED_FILES=(
    ".env"
    ".env.local"
    ".env.local.php"
    ".env.prod"
    ".env.prod.local"
    ".env.dev.local"
    "*.log"
    "*.pid"
    "*.sqlite"
    "*.sqlite3"
    "*.db"
    "*.pem"
    "*.key"
    "*.crt"
    "*.p12"
    "*.pfx"
    "*.bak"
    "*.tmp"
    "*.cache"
    "*.lock"
)

# ------------------------------------------------------------
# Extensiones que sí queremos analizar
# ------------------------------------------------------------

INCLUDED_EXTENSIONS=(
    "php"
    "twig"
    "yaml"
    "yml"
    "xml"
    "json"
    "js"
    "ts"
    "tsx"
    "jsx"
    "css"
    "scss"
    "html"
    "md"
    "neon"
    "dist"
    "conf"
    "ini"
    "sh"
    "sql"
    "dockerfile"
)

# ------------------------------------------------------------
# Funciones
# ------------------------------------------------------------

is_excluded_dir() {
    local path="$1"

    for dir in "${EXCLUDED_DIRS[@]}"; do
        if [[ "$path" == "./$dir" || "$path" == "./$dir/"* ]]; then
            return 0
        fi
    done

    return 1
}

is_excluded_file() {
    local file="$1"
    local basename

    basename=$(basename "$file")

    for pattern in "${EXCLUDED_FILES[@]}"; do
        if [[ "$basename" == $pattern ]]; then
            return 0
        fi

        if [[ "$file" == $pattern ]]; then
            return 0
        fi
    done

    return 1
}

is_included_file() {
    local file="$1"
    local basename
    local extension

    basename=$(basename "$file")

    # Dockerfile no tiene extensión
    if [[ "$basename" == "Dockerfile"* ]]; then
        return 0
    fi

    # Composer
    if [[ "$basename" == "composer.json" ||
          "$basename" == "composer.lock" ]]; then
        return 0
    fi

    # Symfony
    if [[ "$basename" == "symfony.lock" ]]; then
        return 0
    fi

    # Package managers
    if [[ "$basename" == "package.json" ||
          "$basename" == "package-lock.json" ||
          "$basename" == "yarn.lock" ||
          "$basename" == "pnpm-lock.yaml" ]]; then
        return 0
    fi

    extension="${basename##*.}"

    for ext in "${INCLUDED_EXTENSIONS[@]}"; do
        if [[ "$extension" == "$ext" ]]; then
            return 0
        fi
    done

    return 1
}

# ------------------------------------------------------------
# Inicio
# ------------------------------------------------------------

echo "Generando dump del proyecto Symfony..."
echo "Salida: $OUTPUT"

rm -f "$OUTPUT"

# ------------------------------------------------------------
# Cabecera
# ------------------------------------------------------------

cat > "$OUTPUT" <<'EOF'
============================================================
SYMFONY PROJECT DUMP
============================================================

Este archivo contiene una representación textual del proyecto
Symfony para análisis técnico.

IMPORTANTE:
- Se excluyen dependencias generadas.
- Se excluyen cachés y logs.
- Se excluyen credenciales y archivos .env reales.
- Se excluyen datos binarios.
- Se incluye estructura y código fuente relevante.

============================================================

EOF

# ------------------------------------------------------------
# Información básica del proyecto
# ------------------------------------------------------------

echo "============================================================" >> "$OUTPUT"
echo "PROJECT INFORMATION" >> "$OUTPUT"
echo "============================================================" >> "$OUTPUT"
echo >> "$OUTPUT"

echo "Directorio: $(pwd)" >> "$OUTPUT"
echo "Fecha exportación: $(date)" >> "$OUTPUT"
echo >> "$OUTPUT"

if command -v php >/dev/null 2>&1; then
    echo "PHP:" >> "$OUTPUT"
    php -v | head -n 1 >> "$OUTPUT"
    echo >> "$OUTPUT"
fi

if command -v composer >/dev/null 2>&1; then
    echo "Composer:" >> "$OUTPUT"
    composer --version >> "$OUTPUT" 2>/dev/null || true
    echo >> "$OUTPUT"
fi

if command -v node >/dev/null 2>&1; then
    echo "Node:" >> "$OUTPUT"
    node --version >> "$OUTPUT"
    echo >> "$OUTPUT"
fi

if command -v npm >/dev/null 2>&1; then
    echo "NPM:" >> "$OUTPUT"
    npm --version >> "$OUTPUT"
    echo >> "$OUTPUT"
fi

# ------------------------------------------------------------
# Estructura del proyecto
# ------------------------------------------------------------

echo "============================================================" >> "$OUTPUT"
echo "PROJECT TREE" >> "$OUTPUT"
echo "============================================================" >> "$OUTPUT"
echo >> "$OUTPUT"

if command -v tree >/dev/null 2>&1; then

    tree -a \
        -I ".git|vendor|node_modules|var|.idea|.vscode|coverage|.phpunit.cache" \
        . >> "$OUTPUT"

else

    find . \
        -type d \
        \( \
            -name ".git" \
            -o -name "vendor" \
            -o -name "node_modules" \
            -o -name ".idea" \
            -o -name ".vscode" \
            -o -name "cache" \
            -o -name "log" \
        \) -prune \
        -o -print >> "$OUTPUT"

fi

echo >> "$OUTPUT"

# ------------------------------------------------------------
# Composer
# ------------------------------------------------------------

for file in \
    "composer.json" \
    "composer.lock" \
    "symfony.lock" \
    "package.json" \
    "package-lock.json" \
    "yarn.lock" \
    "pnpm-lock.yaml"
do

    if [[ -f "$file" ]]; then

        echo "============================================================" >> "$OUTPUT"
        echo "FILE: $file" >> "$OUTPUT"
        echo "============================================================" >> "$OUTPUT"

        cat "$file" >> "$OUTPUT"

        echo >> "$OUTPUT"
        echo >> "$OUTPUT"

    fi

done

# ------------------------------------------------------------
# Buscar archivos fuente
# ------------------------------------------------------------

echo "Procesando archivos fuente..."

while IFS= read -r -d '' file; do

    # Normalizar path
    clean_file="${file#./}"

    # Excluir directorios
    if is_excluded_dir "$file"; then
        continue
    fi

    # Excluir archivos
    if is_excluded_file "$file"; then
        continue
    fi

    # Solo archivos permitidos
    if ! is_included_file "$file"; then
        continue
    fi

    # Evitar el propio dump
    if [[ "$clean_file" == "$OUTPUT" ]]; then
        continue
    fi

    echo "Incluyendo: $clean_file"

    {
        echo
        echo "============================================================"
        echo "FILE: $clean_file"
        echo "============================================================"
        echo

        cat "$file"

        echo
        echo
    } >> "$OUTPUT"

done < <(
    find . \
        -type d \
        \( \
            -name ".git" \
            -o -name "vendor" \
            -o -name "node_modules" \
            -o -name ".idea" \
            -o -name ".vscode" \
            -o -path "./var/cache" \
            -o -path "./var/log" \
            -o -path "./var/sessions" \
            -o -path "./coverage" \
            -o -path "./.phpunit.cache" \
        \) -prune \
        -o -type f -print0
)

# ------------------------------------------------------------
# Final
# ------------------------------------------------------------

cat >> "$OUTPUT" <<'EOF'

============================================================
END OF PROJECT DUMP
============================================================
EOF

echo
echo "============================================================"
echo "EXPORTACIÓN COMPLETADA"
echo "============================================================"
echo
echo "Archivo generado:"
echo "  $OUTPUT"
echo

if command -v du >/dev/null 2>&1; then
    echo "Tamaño:"
    du -h "$OUTPUT"
fi

echo
echo "IMPORTANTE:"
echo "Revisa el archivo antes de compartirlo para asegurarte de"
echo "que no contenga secretos escritos directamente en el código."
echo
