#!/bin/bash

# Nastavitev imena izhodne datoteke
OUTPUT_FILE="code_export.txt"

# Odstrani izhodno datoteko, če že obstaja, da začnemo s čisto datoteko
if [ -f "$OUTPUT_FILE" ]; then
    rm "$OUTPUT_FILE"
fi

# Preveri, če je to git repozitorij
if git rev-parse --git-dir > /dev/null 2>&1; then
    # Uporabi git ls-files za pridobivanje samo sledenih datotek (avtomatsko upošteva .gitignore)
    FILES=$(git ls-files | sort)
else
    # Če ni git repozitorij, uporabi find za vse datoteke (upošteva .gitignore, če obstaja)
    FILES=$(find . -type f -not -path '*/\.*' | sed 's|^\./||' | sort)
fi

echo "$FILES" | while read -r file; do
    
    # Preskoči prazne vrstice
    if [[ -z "$file" ]]; then
        continue
    fi
    
    # Preskoči samo izhodno datoteko in to skripto
    if [[ "$file" == "$OUTPUT_FILE" || "$file" == "export_code.sh" ]]; then
        continue
    fi
    
    # Preskoči binarne datoteke po končnici
    if [[ "$file" =~ \.(jpg|jpeg|png|gif|bmp|tiff|svg|ico|webp)$ ]]; then
        continue
    fi
    if [[ "$file" =~ \.(mp4|mov|avi|mkv|webm)$ ]]; then
        continue
    fi
    if [[ "$file" =~ \.(mp3|wav|ogg|flac)$ ]]; then
        continue
    fi
    if [[ "$file" =~ \.(pdf)$ ]]; then
        continue
    fi
    if [[ "$file" =~ \.(zip|tar|gz|bz2|rar|7z)$ ]]; then
        continue
    fi
    if [[ "$file" =~ \.(doc|docx|xls|xlsx|ppt|pptx)$ ]]; then
        continue
    fi
    if [[ "$file" =~ \.(eot|ttf|woff|woff2)$ ]]; then
        continue
    fi
    
    # Dodatna varnostna preverba: preskoči binarne datoteke po MIME tipu
    if file --mime-type -b "$file" | grep -qiE '^(image|video|audio|application/(pdf|zip|x-tar|x-gzip|x-bzip2|x-rar|x-7z-compressed|msword|vnd\.ms-|vnd\.openxmlformats))'; then
        continue
    fi
    
    # Dodaj ime datoteke in njeno vsebino v izhodno datoteko
    echo "\"$file\" : " >> "$OUTPUT_FILE"
    echo "\"\"\""  >> "$OUTPUT_FILE"
    cat "$file" >> "$OUTPUT_FILE"
    echo "\"\"\""  >> "$OUTPUT_FILE"
    echo "" >> "$OUTPUT_FILE"
    echo "" >> "$OUTPUT_FILE"
done

echo "Izvoz kode končan. Vsebina je shranjena v datoteko $OUTPUT_FILE"
