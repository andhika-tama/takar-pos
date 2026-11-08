import math
from collections import defaultdict
from statistics import mean, stdev

import numpy as np
import pandas as pd
from statsforecast import StatsForecast
from statsforecast.models import (
    TSB,
    CrostonClassic,
    CrostonSBA,
    SimpleExponentialSmoothingOptimized,
    WindowAverage,
)

BASELINE = "MA4"
HORIZON = 4
MIN_WEEKS = 8
FALLBACK_WEEKS = 4
MAX_WINDOWS = 12
ADI_CUTOFF = 1.32
CV2_CUTOFF = 0.49


def models() -> list:
    return [
        WindowAverage(4, alias=BASELINE),
        SimpleExponentialSmoothingOptimized(alias="SES"),
        CrostonClassic(alias="Croston"),
        CrostonSBA(alias="SBA"),
        TSB(alpha_d=0.2, alpha_p=0.2, alias="TSB"),
    ]


def n_windows(weeks: int) -> int:
    return min(MAX_WINDOWS, weeks - 7)


def classify(weekly_sales: list[int]) -> str:
    """Syntetos-Boylan demand class from the intervals and sizes of non-zero weeks."""
    demands = [week for week in weekly_sales if week > 0]

    if not demands:
        return "intermittent"

    adi = len(weekly_sales) / len(demands)
    cv2 = (float(np.std(demands)) / mean(demands)) ** 2

    if adi < ADI_CUTOFF:
        return "smooth" if cv2 < CV2_CUTOFF else "erratic"

    return "intermittent" if cv2 < CV2_CUTOFF else "lumpy"


def pick_method(candidates: dict[str, dict]) -> str:
    """Lowest backtest WAPE among methods that forecast something; ties stay with the baseline."""
    best, best_wape = BASELINE, candidates[BASELINE]["wape"]

    if candidates[BASELINE]["all_zero"] or math.isnan(best_wape):
        best_wape = math.inf

    for method, candidate in candidates.items():
        if not candidate["all_zero"] and not math.isnan(candidate["wape"]) and candidate["wape"] < best_wape:
            best, best_wape = method, candidate["wape"]

    return best


def _deviation(rows: pd.DataFrame, method: str) -> float:
    """Sample deviation of 1-step residuals; one window has a single 1-step row, so it uses all horizons."""
    one_step = rows[rows["ds"] - rows["cutoff"] == 1]
    source = one_step if len(one_step) >= 2 else rows
    residuals = source["y"] - source[method]

    return float(residuals.std(ddof=1)) if len(residuals) >= 2 else 0.0


def _backtested(items: list[dict]) -> dict[int, dict]:
    """Forecasts for series with enough history, grouped by length so each group shares its windows."""
    by_length: dict[int, list[dict]] = defaultdict(list)

    for item in items:
        by_length[len(item["weekly_sales"])].append(item)

    results = {}

    for weeks, group in by_length.items():
        frame = pd.DataFrame(
            {
                "unique_id": np.repeat([item["product_id"] for item in group], weeks),
                "ds": np.tile(np.arange(1, weeks + 1), len(group)),
                "y": np.concatenate([item["weekly_sales"] for item in group]).astype(float),
            }
        )
        engine = StatsForecast(models=models(), freq=1, n_jobs=1)
        backtest = engine.cross_validation(df=frame, h=HORIZON, step_size=1, n_windows=n_windows(weeks))
        future = engine.forecast(df=frame, h=HORIZON)

        for item in group:
            product_id = item["product_id"]
            rows = backtest[backtest["unique_id"] == product_id]
            actual = float(rows["y"].abs().sum())

            candidates = {
                model.alias: {
                    "wape": float((rows["y"] - rows[model.alias]).abs().sum()) / actual if actual else math.nan,
                    "all_zero": bool((rows[model.alias].abs() < 1e-9).all()),
                }
                for model in models()
            }
            method = pick_method(candidates)
            forecast = [max(0.0, float(value)) for value in future.loc[future["unique_id"] == product_id, method]]

            results[product_id] = {
                "status": "ok",
                "demand_class": classify(item["weekly_sales"]),
                "method": method,
                "forecast": forecast,
                "weekly_rate": mean(forecast),
                "demand_deviation": _deviation(rows, method),
            }

    return results


def _category_means(items: list[dict]) -> dict[int, float]:
    rates: dict[int, list[float]] = defaultdict(list)

    for item in items:
        if len(item["weekly_sales"]) >= FALLBACK_WEEKS:
            rates[item["category_id"]].append(mean(item["weekly_sales"]))

    return {category: mean(values) for category, values in rates.items()}


def _fallback(item: dict, category_means: dict[int, float]) -> dict:
    sales = item["weekly_sales"]

    if len(sales) >= FALLBACK_WEEKS:
        method, rate, deviation = "AVG", float(mean(sales)), float(stdev(sales))
    elif item["category_id"] in category_means:
        method, rate, deviation = "CATEGORY", float(category_means[item["category_id"]]), 0.0
    else:
        method, rate, deviation = None, None, None

    return {
        "status": "insufficient_data",
        "demand_class": None,
        "method": method,
        "forecast": [] if rate is None else [rate] * HORIZON,
        "weekly_rate": rate,
        "demand_deviation": deviation,
    }


def forecast_items(items: list[dict]) -> dict[int, dict]:
    """Every product's forecast and 4-week moving-average baseline, keyed by product id."""
    long_enough = [item for item in items if len(item["weekly_sales"]) >= MIN_WEEKS]
    results = _backtested(long_enough) if long_enough else {}
    category_means = _category_means(items)

    for item in items:
        product_id = item["product_id"]

        if product_id not in results:
            results[product_id] = _fallback(item, category_means)

        recent = item["weekly_sales"][-FALLBACK_WEEKS:]
        results[product_id]["baseline"] = [float(mean(recent))] * HORIZON if recent else []

    return results
