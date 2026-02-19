#!/usr/bin/env python3
"""
Comprehensive Twig template syntax checker for SmartMoons
Identifies common syntax errors from old Smarty conversions
"""
import os
import re
from pathlib import Path
from typing import List, Dict, Tuple

def check_twig_file(filepath: str) -> List[Dict]:
    """Check a single Twig file for syntax errors"""
    errors = []
    
    with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
        content = f.read()
        lines = content.split('\n')
    
    for line_num, line in enumerate(lines, 1):
        # Check for triple closing braces: }}}
        if '}}}' in line:
            errors.append({
                'file': filepath,
                'line': line_num,
                'type': 'triple_brace',
                'content': line.strip(),
                'issue': 'Triple closing brace }}}'
            })
        
        # Check for space before closing braces in variables: {{ var } }}
        if re.search(r'\{\{[^}]*\}\s+\}\}', line):
            errors.append({
                'file': filepath,
                'line': line_num,
                'type': 'space_before_close',
                'content': line.strip(),
                'issue': 'Space before closing }} in variable block'
            })
        
        # Check for malformed variable blocks: {{ var }
        # Exclude valid cases with hash literals like replace({'.git':''})
        if re.search(r'\{\{[^}]+\}(?!\})', line):
            # Make sure it's not a valid {% block %}
            # Also exclude lines with hash literals (filter arguments like {'.git':''})
            if not re.search(r'\{%.*%\}', line) and not re.search(r'\([^)]*\{[^}]*\}[^)]*\)', line):
                errors.append({
                    'file': filepath,
                    'line': line_num,
                    'type': 'single_close_var',
                    'content': line.strip(),
                    'issue': 'Single closing brace } after variable {{ - should be }}'
                })
        
        # Check for space before %}: {% if x } %}
        # But exclude valid cases like with {'key': 'value'} %}
        if re.search(r'\{%\s+(?:if|for|set|elseif|elif)[^}]*\}\s+%\}', line):
            # Double check it's not a valid 'with' statement
            if not re.search(r'with\s+\{[^}]+\}\s+%\}', line):
                errors.append({
                    'file': filepath,
                    'line': line_num,
                    'type': 'space_before_percent',
                    'content': line.strip(),
                    'issue': 'Space before %} in logic block'
                })
        
        # Check for unclosed variable blocks: {{ var
        if re.search(r'\{\{[^}]*$', line) and not re.search(r'\}\}', line):
            errors.append({
                'file': filepath,
                'line': line_num,
                'type': 'unclosed_var',
                'content': line.strip(),
                'issue': 'Unclosed variable block {{'
            })
        
        # Check for unclosed logic blocks: {% if
        if re.search(r'\{%[^%]*$', line) and not re.search(r'%\}', line):
            errors.append({
                'file': filepath,
                'line': line_num,
                'type': 'unclosed_logic',
                'content': line.strip(),
                'issue': 'Unclosed logic block {%'
            })
    
    # Check block balance
    if_count = content.count('{% if ')
    endif_count = content.count('{% endif %}')
    if if_count != endif_count:
        errors.append({
            'file': filepath,
            'line': 0,
            'type': 'unbalanced_if',
            'content': f'if: {if_count}, endif: {endif_count}',
            'issue': f'Unbalanced if/endif blocks: {if_count} ifs vs {endif_count} endifs'
        })
    
    for_count = content.count('{% for ')
    endfor_count = content.count('{% endfor %}')
    if for_count != endfor_count:
        errors.append({
            'file': filepath,
            'line': 0,
            'type': 'unbalanced_for',
            'content': f'for: {for_count}, endfor: {endfor_count}',
            'issue': f'Unbalanced for/endfor blocks: {for_count} fors vs {endfor_count} endfors'
        })
    
    block_count = content.count('{% block ')
    endblock_count = content.count('{% endblock %}')
    if block_count != endblock_count:
        errors.append({
            'file': filepath,
            'line': 0,
            'type': 'unbalanced_block',
            'content': f'block: {block_count}, endblock: {endblock_count}',
            'issue': f'Unbalanced block/endblock: {block_count} blocks vs {endblock_count} endblocks'
        })
    
    return errors

def scan_templates(base_path: str) -> Dict:
    """Scan all Twig templates in the given path"""
    all_errors = []
    files_checked = 0
    
    for root, dirs, files in os.walk(base_path):
        for file in files:
            if file.endswith('.twig'):
                filepath = os.path.join(root, file)
                files_checked += 1
                errors = check_twig_file(filepath)
                all_errors.extend(errors)
    
    return {
        'files_checked': files_checked,
        'errors': all_errors,
        'error_count': len(all_errors)
    }

if __name__ == '__main__':
    template_path = '/workspace/styles/templates'
    
    print(f"Scanning Twig templates in: {template_path}")
    print("=" * 80)
    
    results = scan_templates(template_path)
    
    print(f"\nFiles checked: {results['files_checked']}")
    print(f"Total errors found: {results['error_count']}")
    print("=" * 80)
    
    if results['errors']:
        print("\nERRORS FOUND:\n")
        
        # Group by file
        errors_by_file = {}
        for error in results['errors']:
            file = error['file']
            if file not in errors_by_file:
                errors_by_file[file] = []
            errors_by_file[file].append(error)
        
        for file, errors in sorted(errors_by_file.items()):
            print(f"\n{file}")
            print("-" * 80)
            for error in errors:
                if error['line'] > 0:
                    print(f"  Line {error['line']}: {error['issue']}")
                    print(f"    {error['content']}")
                else:
                    print(f"  File-level: {error['issue']}")
                    print(f"    {error['content']}")
        
        print("\n" + "=" * 80)
        print(f"Summary: {len(errors_by_file)} files with errors")
    else:
        print("\n✅ No syntax errors found! All templates appear to be valid.")
