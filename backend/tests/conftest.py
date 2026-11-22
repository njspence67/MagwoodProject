import pytest
from fastapi.testclient import TestClient

from herd_api.main import create_app


@pytest.fixture
def client():
    # Each test owns a new repository; no mutation of the module-level app.
    with TestClient(create_app()) as test_client:
        yield test_client
