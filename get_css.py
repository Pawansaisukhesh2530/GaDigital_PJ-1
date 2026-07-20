import os

with open("assets/CSS/style.css", 'r', encoding='utf-8', errors='ignore') as f:
    css = f.read()

import re
# Find all blocks containing partners-title
blocks = re.findall(r'[^}]*partners-title[^}]*}', css)
for b in blocks:
    print(b)
    print("-----")
