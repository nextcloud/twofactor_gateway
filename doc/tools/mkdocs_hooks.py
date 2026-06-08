# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

from __future__ import annotations

from pathlib import Path
import shutil
import subprocess

OPENAPI_FILES = (
    'openapi.json',
    'openapi-administration.json',
    'openapi-full.json',
)

STYLEGUIDE_SOURCE_DIR = ('build', 'styleguide')
STYLEGUIDE_SITE_DIR = 'frontend'

_STYLEGUIDE_BUILT = False


def _project_root() -> Path:
    current_path = Path(__file__).resolve()

    for parent in current_path.parents:
        if (parent / 'mkdocs.yml').exists() and (parent / 'package.json').exists():
            return parent

    raise RuntimeError(f'Unable to resolve project root from {current_path}')


def _copy_directory(source: Path, target: Path) -> None:
    if target.exists():
        shutil.rmtree(target)
    shutil.copytree(source, target)


def _build_styleguide(project_root: Path) -> None:
    global _STYLEGUIDE_BUILT

    if _STYLEGUIDE_BUILT:
        return

    subprocess.run(
        ['npm', 'run', 'styleguide:build'],
        cwd=project_root,
        check=True,
    )
    _STYLEGUIDE_BUILT = True


def on_pre_build(config, **kwargs) -> None:
    _build_styleguide(_project_root())


def on_post_build(config, **kwargs) -> None:
    project_root = _project_root()
    site_dir = Path(config['site_dir']).resolve()

    styleguide_source = project_root.joinpath(*STYLEGUIDE_SOURCE_DIR)
    if not styleguide_source.exists():
        raise FileNotFoundError(f'Missing styleguide build output: {styleguide_source}')
    _copy_directory(styleguide_source, site_dir / STYLEGUIDE_SITE_DIR)

    target_dir = site_dir / 'openapi'
    target_dir.mkdir(parents=True, exist_ok=True)

    for file_name in OPENAPI_FILES:
        source = project_root / file_name
        if not source.exists():
            raise FileNotFoundError(f'Missing OpenAPI artifact: {source}')
        shutil.copy2(source, target_dir / file_name)
