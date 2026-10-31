import os
import pathlib
import subprocess
import tempfile
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[1]
SCRIPT = ROOT / 'scripts/install-release.sh'


class InstallerTests(unittest.TestCase):
    def test_requires_explicit_install_flag(self):
        result = subprocess.run(['bash', str(SCRIPT)], capture_output=True, text=True)
        self.assertNotEqual(result.returncode, 0)

    def test_invalid_dry_run_fails_closed(self):
        env = dict(os.environ, DRY_RUN='yes')
        result = subprocess.run(['bash', str(SCRIPT), '--install'], env=env, capture_output=True, text=True)
        self.assertNotEqual(result.returncode, 0)

    def test_dry_run_never_executes_install_commands(self):
        with tempfile.TemporaryDirectory() as tmp:
            marker = pathlib.Path(tmp) / 'called'
            for name in ('php', 'composer', 'node', 'npm'):
                executable = pathlib.Path(tmp) / name
                executable.write_text(f'#!/bin/sh\necho called >> "{marker}"\n')
                executable.chmod(0o755)
            env = dict(os.environ, PATH=tmp + os.pathsep + os.environ['PATH'], DRY_RUN='1')
            result = subprocess.run(['bash', str(SCRIPT), '--install'], env=env, capture_output=True, text=True)
            self.assertEqual(result.returncode, 0, result.stderr)
            self.assertFalse(marker.exists())
            self.assertIn('composer install', result.stdout)
            self.assertIn('npm ci', result.stdout)
            self.assertIn('npm run build', result.stdout)

if __name__ == '__main__':
    unittest.main()
