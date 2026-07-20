import os
import glob
import re

php_files = glob.glob('*.php')

for filepath in php_files:
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # Special case for index.php -> /
    # But only if it's href="index.php" (not in some text)
    # Be careful not to replace it if it's in a URL like href="https://example.com/index.php"
    # Assuming relative links like href="index.php"
    
    # Replace href="index.php" with href="/"
    content = re.sub(r'href="index\.php"', r'href="/"', content)
    
    # Replace other local .php files
    # E.g., href="about.php" -> href="about"
    # Match href="[some letters/dashes/underscores].php"
    content = re.sub(r'href="([a-zA-Z0-9_\-]+)\.php(\?[^"]*)?"', r'href="\1\2"', content)
    
    # Also replace in PHP strings, if any, e.g. header("Location: index.php");
    # Not strictly required if we just focus on HTML links, but let's be thorough if needed.
    # Actually, let's just stick to href to be safe.

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)

print("Updated links in all PHP files!")
