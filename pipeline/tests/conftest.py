import json
import sys
from pathlib import Path

import pytest

PIPELINE = Path(__file__).resolve().parents[1]
if str(PIPELINE) not in sys.path:
    sys.path.insert(0, str(PIPELINE))


@pytest.fixture(scope="session")
def pipeline_dir():
    return PIPELINE


def _load(path):
    if not path.exists():
        pytest.skip(f"thiếu file mẫu {path}")
    return json.loads(path.read_text(encoding="utf-8"))


@pytest.fixture(scope="session")
def load_json():
    return _load


@pytest.fixture(scope="session")
def output_dir(pipeline_dir):
    return pipeline_dir / "output"


@pytest.fixture(scope="session")
def data_dir(pipeline_dir):
    return pipeline_dir / "data"
