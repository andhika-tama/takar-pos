from fastapi import FastAPI

from app.forecasting import forecast_items
from app.restock import suggest
from app.schemas import (
    ForecastRequest,
    ForecastResponse,
    ForecastResult,
    RestockRequest,
    RestockResponse,
    RestockResult,
)

app = FastAPI(title="Takar Forecast")

ROUNDED = ("weekly_rate", "demand_deviation", "safety_stock", "reorder_point", "target_level")


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/forecast")
def forecast(request: ForecastRequest) -> ForecastResponse:
    results = forecast_items([item.model_dump() for item in request.items])

    return ForecastResponse(
        results=[ForecastResult(product_id=item.product_id, **_public(results[item.product_id])) for item in request.items]
    )


@app.post("/restock")
def restock(request: RestockRequest) -> RestockResponse:
    forecasts = forecast_items([item.model_dump() for item in request.items])
    results = []

    for item in request.items:
        result = forecasts[item.product_id]
        policy = {"safety_stock": None, "reorder_point": None, "target_level": None, "suggested_quantity": None}

        if item.lead_time_days is None:
            result["status"] = "missing_lead_time"
        elif result["weekly_rate"] is not None:
            policy = suggest(
                rate=result["weekly_rate"],
                deviation=result["demand_deviation"],
                lead_time_days=item.lead_time_days,
                review_period_days=request.review_period_days,
                service_level=request.service_level,
                on_hand=item.on_hand,
                on_order=item.on_order,
                min_order_qty=item.min_order_qty,
                pack_size=item.pack_size,
            )

        merged = result | policy
        results.append(
            RestockResult(
                product_id=item.product_id,
                **_public(merged),
                **{key: None if merged[key] is None else round(merged[key], 4) for key in ROUNDED},
                suggested_quantity=merged["suggested_quantity"],
            )
        )

    return RestockResponse(results=results)


def _public(result: dict) -> dict:
    return {
        "status": result["status"],
        "demand_class": result["demand_class"],
        "method": result["method"],
        "forecast": [round(value, 4) for value in result["forecast"]],
        "baseline": [round(value, 4) for value in result["baseline"]],
    }
