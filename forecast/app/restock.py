import math

Z_SCORES = {90: 1.28, 95: 1.65}
SLOW_MOVER_RATE = 1.0


def suggest(
    *,
    rate: float,
    deviation: float,
    lead_time_days: int,
    review_period_days: int,
    service_level: int,
    on_hand: int,
    on_order: int,
    min_order_qty: int,
    pack_size: int,
) -> dict[str, float | int]:
    """Reorder point and order quantity under FR-042 and FR-043. Rate and deviation are per week."""
    lead_demand = rate * lead_time_days / 7
    safety_stock = Z_SCORES[service_level] * deviation * math.sqrt(lead_time_days / 7)

    if rate < SLOW_MOVER_RATE:
        safety_stock = min(safety_stock, 2 * lead_demand)

    reorder_point = lead_demand + safety_stock
    target_level = rate * (lead_time_days + review_period_days) / 7 + safety_stock
    position = on_hand + on_order
    raw = target_level - position

    quantity = 0

    if position <= reorder_point and raw > 0:
        pack = max(pack_size, 1)
        quantity = math.ceil(max(raw, min_order_qty) / pack) * pack

    return {
        "safety_stock": safety_stock,
        "reorder_point": reorder_point,
        "target_level": target_level,
        "suggested_quantity": quantity,
    }
