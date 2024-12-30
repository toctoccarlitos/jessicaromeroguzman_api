import os
import fnmatch
import subprocess
import sys
import platform
import locale

def sanitize_path(path):
    """Sanitize Windows paths for subprocess calls"""
    if platform.system() == "Windows":
        return os.path.normpath(path).replace('\\', '/')
    return path

def get_tree_structure(project_dir):
    """Get directory tree structure in a cross-platform way"""
    project_dir = sanitize_path(project_dir)

    if platform.system() == "Windows":
        try:
            # Use Windows' built-in tree command with appropriate flags
            si = subprocess.STARTUPINFO()
            si.dwFlags |= subprocess.STARTF_USESHOWWINDOW
            result = subprocess.run(['tree', '/F', '/A'],
                                 cwd=project_dir,
                                 capture_output=True,
                                 text=True,
                                 encoding=locale.getpreferredencoding(),
                                 startupinfo=si,
                                 check=True)
            # Remove the first two lines of Windows tree output
            tree_lines = result.stdout.split('\n')[2:]
            return '\n'.join(tree_lines)
        except (subprocess.CalledProcessError, Exception) as e:
            print(f"Tree command failed: {e}. Using manual tree generation.")
            return create_manual_tree(project_dir)
    else:
        try:
            exclude_pattern = 'node_modules|.vscode|venv|temp|uploads|output|datastore|input|__pycache__|code.txt|code_extractor.py|target|.idea|.mvn|.settings|bin|build|out|logs|vendor|local'
            result = subprocess.run(['tree', '-I', exclude_pattern],
                                 cwd=project_dir,
                                 capture_output=True,
                                 text=True,
                                 check=True)
            return result.stdout
        except (FileNotFoundError, subprocess.CalledProcessError):
            return create_manual_tree(project_dir)

def create_manual_tree(start_path):
    """Create a tree-like structure manually if tree command fails"""
    output = []
    exclude_dirs = {
        'node_modules', '.vscode', 'venv', 'temp', 'uploads', 'output',
        'datastore', 'input', '__pycache__', 'target', '.idea', '.mvn',
        '.settings', 'bin', 'build', 'out', 'logs', 'dist','vendor'
    }

    try:
        for root, dirs, files in os.walk(start_path):
            # Remove excluded directories
            dirs[:] = [d for d in dirs if d not in exclude_dirs and not d.startswith('.')]

            # Calculate current depth and prepare indentation
            level = root[len(start_path):].count(os.sep)
            indent = '│   ' * (level - 1) + '├── ' if level > 0 else ''

            # Add directory name
            if level > 0:
                dirname = os.path.basename(root)
                output.append(f"{indent}{dirname}")

            # Add files
            subindent = '│   ' * level + '├── '
            for f in sorted(files):
                if not f.startswith('.') and not any(f.endswith(ext) for ext in ['.pyc', '.pyo']):
                    output.append(f"{subindent}{f}")

        return '\n'.join(output)
    except Exception as e:
        print(f"Error creating manual tree: {e}")
        return "Error creating directory tree"

def is_media_file(filename):
    media_extensions = {
        '.jpg', '.jpeg', '.png', '.gif', '.bmp', '.tiff', '.webp',  # Images
        '.mp4', '.avi', '.mov', '.wmv', '.flv', '.mkv',  # Videos
        '.mp3', '.wav', '.ogg', '.flac', '.aac',  # Audio
        '.svg',  # Vector graphics
    }
    return os.path.splitext(filename)[1].lower() in media_extensions

def process_files(project_dir, output_file):
    if os.path.exists(output_file):
        try:
            os.remove(output_file)
            print(f"Removed existing output file: {output_file}")
        except OSError as e:
            print(f"Error removing existing output file: {e}")
            sys.exit(1)

    section = "frontend" if os.path.isfile(os.path.join(project_dir, "package.json")) else "backend"

    exclude_files = {
        'package-lock.json', 'yarn.lock', 'tsconfig.json', 'tsconfig.node.json',
        'README.md', '.gitignore', '.eslintrc.js', '.prettierrc', 'babel.config.js',
        'jest.config.js', 'webpack.config.js', 'requirements.txt', 'Pipfile',
        'Pipfile.lock', 'setup.py', 'MANIFEST.in', 'code_extractor.py', 'code.txt', 'code_extractor_back.py', 'code_extractor_front.py'
    }
    exclude_patterns = ['*.md', '*.lock', '*.log', '*.txt', '*.yml', '*.yaml', 'LICENSE*', '.env*', '*.json', '*.pyc', '*.pyo', '*.map', '*.min.*', '*.swp', '*.swo', '.git*']
    exclude_dirs = {'node_modules', 'venv', 'temp', 'uploads', 'output', 'datastore', 'input', '__pycache__', 'build', 'dist', 'tests', 'vendor', 'local'}

    try:
        with open(output_file, 'w', encoding='utf-8') as out:
            out.write(f"This is my {section} code:\n\n")

            out.write("Project Structure:\n")
            out.write(get_tree_structure(project_dir))
            out.write("\n\nFile Contents:\n\n")

            for root, dirs, files in os.walk(project_dir):
                dirs[:] = [d for d in dirs if not d.startswith('.') or d == '.env']
                dirs[:] = [d for d in dirs if d not in exclude_dirs]

                for file in files:
                    if file.startswith('.') and file != '.env':
                        continue

                    if file in exclude_files or any(fnmatch.fnmatch(file, pattern) for pattern in exclude_patterns):
                        continue

                    if is_media_file(file):
                        continue  # Skip media files entirely

                    file_path = os.path.join(root, file)
                    relative_path = os.path.relpath(file_path, project_dir)

                    if 'tests' in relative_path.split(os.sep):
                        continue

                    out.write(f"File: {relative_path}\n")
                    out.write("------------------------\n")

                    try:
                        with open(file_path, 'r', encoding='utf-8') as f:
                            out.write(f.read())
                    except UnicodeDecodeError:
                        out.write("Binary file, contents not shown.\n")
                    except IOError as e:
                        out.write(f"Error reading file: {e}\n")

                    out.write("\n\n")

        print(f"Script completed. Output saved to {output_file}")
    except IOError as e:
        print(f"Error writing to output file: {e}")
        sys.exit(1)

if __name__ == "__main__":
    script_dir = os.path.dirname(os.path.abspath(__file__))
    project_dir = os.getcwd()
    output_file = os.path.join(script_dir, "code.txt")

    process_files(project_dir, output_file)
