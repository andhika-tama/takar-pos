import math

from app.restock import suggest

DAYS_PER_WEEK = 7


def simulate(
    demand: list[float],
    *,
    rates: list[float],
    deviations: list[float],
    initial_stock: float,
    lead_time_days: int = 7,
    review_period_days: int = 7,
    service_level: int = 90,
) -> dict[str, float]:
    """
    Runs the app's reorder policy week by week against actual demand.

    Each Monday any due order arrives and a new one may be placed from that week's forecast.
    Demand is spread evenly over the days. A day that cannot be met in full is a stockout day,
    and unmet demand is lost rather than back-ordered.
    """
    on_hand = float(initial_stock)
    arrivals: dict[int, int] = {}
    lead_weeks = max(1, math.ceil(lead_time_days / DAYS_PER_WEEK))
    stockout_days = 0
    closing_stock = []

    for week, units in enumerate(demand):
        on_hand += arrivals.pop(week, 0)

        order = suggest(
            rate=rates[week],
            deviation=deviations[week],
            lead_time_days=lead_time_days,
            review_period_days=review_period_days,
            service_level=service_level,
            on_hand=on_hand,
            on_order=sum(arrivals.values()),
            min_order_qty=1,
            pack_size=1,
        )["suggested_quantity"]

        if order > 0:
            arrivals[week + lead_weeks] = arrivals.get(week + lead_weeks, 0) + order

        for _ in range(DAYS_PER_WEEK):
            wanted = units / DAYS_PER_WEEK

            if on_hand + 1e-9 < wanted:
                stockout_days += 1

            on_hand = max(0.0, on_hand - wanted)
            closing_stock.append(on_hand)

    return {"stockout_days": stockout_days, "average_stock": sum(closing_stock) / len(closing_stock)}
