from typing import Annotated, Literal

from pydantic import BaseModel, Field

Status = Literal["ok", "insufficient_data", "missing_lead_time"]
DemandClass = Literal["smooth", "erratic", "intermittent", "lumpy"]
Method = Literal["MA4", "SES", "Croston", "SBA", "TSB", "AVG", "CATEGORY"]
Units = Annotated[int, Field(ge=0)]


class ForecastItem(BaseModel):
    product_id: int
    category_id: int
    weekly_sales: list[Units]


class ForecastRequest(BaseModel):
    items: list[ForecastItem]


class RestockItem(ForecastItem):
    on_hand: Units
    on_order: Units
    lead_time_days: Units | None
    min_order_qty: int = Field(ge=1)
    pack_size: Units


class RestockRequest(BaseModel):
    service_level: Literal[90, 95]
    review_period_days: int = Field(ge=1)
    items: list[RestockItem]


class ForecastResult(BaseModel):
    product_id: int
    status: Status
    demand_class: DemandClass | None
    method: Method | None
    forecast: list[float]
    baseline: list[float]


class RestockResult(ForecastResult):
    weekly_rate: float | None
    demand_deviation: float | None
    safety_stock: float | None
    reorder_point: float | None
    target_level: float | None
    suggested_quantity: int | None


class ForecastResponse(BaseModel):
    results: list[ForecastResult]


class RestockResponse(BaseModel):
    results: list[RestockResult]
