import os
import glob

css_files = glob.glob("assets/CSS/*.css")
for file in css_files:
    with open(file, 'r', encoding='utf-8', errors='ignore') as f:
        lines = f.readlines()
        for i, line in enumerate(lines):
            if "partners-title" in line:
                print(f"Found partners-title in {file}:{i+1} - {line.strip()}")
            if "expertise-partners-section h2" in line:
                print(f"Found expertise-partners-section h2 in {file}:{i+1} - {line.strip()}")
