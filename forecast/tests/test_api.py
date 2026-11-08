import json

import pytest
from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def stock_item(**overrides):
    return {
        "product_id": 12,
        "category_id": 3,
        "weekly_sales": [4, 0, 7, 3, 5, 0, 2, 6],
        "on_hand": 8,
        "on_order": 0,
        "lead_time_days": 5,
        "min_order_qty": 1,
        "pack_size": 6,
    } | overrides


def restock(*items, service_level=90):
    return client.post(
        "/restock",
        json={"service_level": service_level, "review_period_days": 7, "items": list(items)},
    )


def test_restock_returns_every_contract_field():
    response = restock(stock_item())

    assert response.status_code == 200
    result = response.json()["results"][0]
    assert set(result) == {
        "product_id", "status", "demand_class", "method", "forecast", "baseline", "weekly_rate",
        "demand_deviation", "safety_stock", "reorder_point", "target_level", "suggested_quantity",
    }
    assert result["product_id"] == 12
    assert result["status"] == "ok"
    assert isinstance(result["suggested_quantity"], int)


@pytest.mark.parametrize("service_level", [80, 91, 100])
def test_any_other_service_level_is_refused(service_level):
    assert restock(stock_item(), service_level=service_level).status_code == 422


def test_a_missing_lead_time_gives_no_quantity():
    result = restock(stock_item(lead_time_days=None)).json()["results"][0]

    assert result["status"] == "missing_lead_time"
    assert result["suggested_quantity"] is None
    assert result["forecast"]


def test_an_unsold_product_sends_an_empty_series():
    response = restock(stock_item(weekly_sales=[]))

    assert response.status_code == 200
    result = response.json()["results"][0]
    assert result["status"] == "insufficient_data"
    assert result["suggested_quantity"] is None


def test_the_fallback_still_suggests_a_quantity():
    result = restock(stock_item(weekly_sales=[6, 6, 6, 6], on_hand=0, pack_size=1)).json()["results"][0]

    assert result["status"] == "insufficient_data"
    assert result["method"] == "AVG"
    assert result["suggested_quantity"] > 0


def test_a_negative_week_is_refused():
    assert restock(stock_item(weekly_sales=[1, -1])).status_code == 422


def test_forecast_needs_no_stock_or_policy_fields():
    response = client.post(
        "/forecast",
        json={"items": [{"product_id": 12, "category_id": 3, "weekly_sales": [4, 0, 7, 3, 5, 0, 2, 6]}]},
    )

    assert response.status_code == 200
    assert set(response.json()["results"][0]) == {
        "product_id", "status", "demand_class", "method", "forecast", "baseline",
    }


def test_results_are_valid_json_with_no_nan():
    body = restock(stock_item(weekly_sales=[0] * 12), stock_item(product_id=13, weekly_sales=[])).text

    json.loads(body, parse_constant=lambda value: pytest.fail(f"{value} in response"))
