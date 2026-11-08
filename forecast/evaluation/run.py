"""Rolling-origin evaluation behind docs/forecast-evaluation.md. See FR-048, FR-049 and SC-008 to SC-010."""

import argparse
import csv
import math
from collections import defaultdict
from pathlib import Path

import numpy as np
import pandas as pd
from statsforecast import StatsForecast

from app.forecasting import BASELINE, HORIZON, classify, models, pick_method
from evaluation.simulation import simulate

MIN_WEEKS = 20
SMALL_SAMPLE = 20
MAX_WINDOWS = 52
METHODS = [BASELINE, "SES", "Croston", "SBA", "TSB"]
COLUMNS = [*METHODS, "Chosen"]
CLASSES = ["smooth", "erratic", "intermittent", "lumpy"]
INTERMITTENT = {"intermittent", "lumpy"}
M5_STORE = "CA_1"
M5_DEPARTMENT = "FOODS_3"
M5_SERIES = 200
M5_SEED = 7


def wape(actual, forecast) -> float:
    volume = float(np.abs(actual).sum())

    return float(np.abs(np.subtract(actual, forecast)).sum()) / volume if volume else math.nan


def mase(actual, forecast, *, history) -> float:
    scale = float(np.abs(np.diff(history)).mean()) if len(history) > 1 else 0.0

    return float(np.abs(np.subtract(actual, forecast)).mean()) / scale if scale else math.nan


def bias(actual, forecast) -> float:
    volume = float(np.abs(actual).sum())

    return float(np.subtract(forecast, actual).sum()) / volume if volume else math.nan


def _backtest(group: dict[int, dict], weeks: int) -> pd.DataFrame:
    frame = pd.DataFrame(
        {
            "unique_id": np.repeat(list(group), weeks),
            "ds": np.tile(np.arange(1, weeks + 1), len(group)),
            "y": np.concatenate([item["sales"] for item in group.values()]).astype(float),
        }
    )
    windows = min(MAX_WINDOWS, weeks - 7)

    return StatsForecast(models=models(), freq=1, n_jobs=1).cross_validation(df=frame, h=HORIZON, step_size=1, n_windows=windows)


def _simulate(rows: pd.DataFrame, cutoffs: list[int], method: str, initial_stock: float) -> dict[str, float]:
    """One decision per cutoff: its 4-week mean, and a deviation from 1-step errors known by then."""
    rates, deviations, demand = [], [], []
    one_step = rows[rows["ds"] - rows["cutoff"] == 1].sort_values("cutoff")

    for cutoff in cutoffs:
        at_cutoff = rows[rows["cutoff"] == cutoff]
        known = one_step[one_step["ds"] <= cutoff]
        errors = known["y"] - known[method]

        rates.append(max(0.0, float(at_cutoff[method].mean())))
        deviations.append(float(errors.std(ddof=1)) if len(errors) >= 2 else 0.0)
        demand.append(float(one_step.loc[one_step["cutoff"] == cutoff, "y"].iloc[0]))

    return simulate(demand, rates=rates, deviations=deviations, initial_stock=initial_stock)


def evaluate(series: dict[int, dict]) -> dict:
    """
    Picks each product's method on the earlier half of its windows and scores everything on the
    later half. Scoring on the windows used for selection would flatter the chosen method.
    """
    eligible = {key: item for key, item in series.items() if len(item["sales"]) >= MIN_WEEKS}
    by_length: dict[int, dict[int, dict]] = defaultdict(dict)

    for key, item in eligible.items():
        by_length[len(item["sales"])][key] = item

    pooled = {name: {column: {"error": 0.0, "signed": 0.0, "volume": 0.0, "mase": []} for column in COLUMNS} for name in CLASSES}
    simulated = {column: {"stockout_days": 0, "average_stock": 0.0} for column in COLUMNS}
    products = []
    selection_windows = scoring_windows = 0

    for weeks, group in by_length.items():
        backtest = _backtest(group, weeks)

        for key, item in group.items():
            rows = backtest[backtest["unique_id"] == key]
            cutoffs = sorted(rows["cutoff"].unique())
            split = len(cutoffs) // 2
            early, late = set(cutoffs[:split]), cutoffs[split:]
            selection_windows = max(selection_windows, split)
            scoring_windows = max(scoring_windows, len(late))

            selecting = rows[rows["cutoff"].isin(early)]
            scoring = rows[rows["cutoff"].isin(late)].assign(Chosen=0.0)

            method = pick_method(
                {
                    name: {
                        "wape": wape(selecting["y"], selecting[name]),
                        "all_zero": bool((selecting[name].abs() < 1e-9).all()),
                    }
                    for name in METHODS
                }
            )
            scoring["Chosen"] = scoring[method]

            demand_class = classify(item["sales"])
            demand_weeks = [item["sales"][int(cutoff)] for cutoff in late]
            history = item["sales"][: int(late[0])]
            chosen_wape = wape(scoring["y"], scoring["Chosen"])
            baseline_wape = wape(scoring["y"], scoring[BASELINE])

            for column in COLUMNS:
                totals = pooled[demand_class][column]
                totals["error"] += float((scoring["y"] - scoring[column]).abs().sum())
                totals["signed"] += float((scoring[column] - scoring["y"]).sum())
                totals["volume"] += float(scoring["y"].abs().sum())
                totals["mase"].append(mase(scoring["y"], scoring[column], history=history))

            start = max(1.0, math.ceil(float(np.mean(history[-4:])) * 2))
            rows_with_chosen = rows.assign(Chosen=rows[method])

            for column in COLUMNS:
                outcome = _simulate(rows_with_chosen, late, column, start)
                simulated[column]["stockout_days"] += outcome["stockout_days"]
                simulated[column]["average_stock"] += outcome["average_stock"]

            products.append(
                {
                    "name": item["name"],
                    "class": demand_class,
                    "method": method,
                    "wape": chosen_wape,
                    "baseline_wape": baseline_wape,
                    "units": float(np.mean(demand_weeks)),
                }
            )

    by_class = {}

    for name, columns in pooled.items():
        if columns[BASELINE]["mase"]:
            by_class[name] = {
                column: {
                    "wape": totals["error"] / totals["volume"] if totals["volume"] else math.nan,
                    "bias": totals["signed"] / totals["volume"] if totals["volume"] else math.nan,
                    "mase": float(np.nanmean(totals["mase"])) if not np.all(np.isnan(totals["mase"])) else math.nan,
                }
                for column, totals in columns.items()
            }

    intermittent = [item for item in products if item["class"] in INTERMITTENT and not math.isnan(item["wape"])]
    beat = [item for item in intermittent if item["wape"] < item["baseline_wape"]]
    count = max(1, len(products))

    return {
        "products": len(products),
        "excluded": len(series) - len(eligible),
        "selection_windows": selection_windows,
        "scoring_windows": scoring_windows,
        "by_class": by_class,
        "class_counts": {name: sum(item["class"] == name for item in products) for name in CLASSES},
        "methods": {name: sum(item["method"] == name for item in products) for name in METHODS},
        "simulation": {
            column: {"stockout_days": totals["stockout_days"], "average_stock": totals["average_stock"] / count}
            for column, totals in simulated.items()
        },
        "intermittent": len(intermittent),
        "intermittent_beat": len(beat),
        "worst": sorted(
            (item for item in products if not math.isnan(item["wape"])),
            key=lambda item: (-item["wape"], item["name"]),
        )[:5],
    }


def _number(value: float, digits: int = 3) -> str:
    return "n/a" if math.isnan(value) else f"{value:.{digits}f}"


def _table(result: dict, metric: str) -> list[str]:
    lines = ["| Class | " + " | ".join(COLUMNS) + " |", "|---" * (len(COLUMNS) + 1) + "|"]

    for name, columns in result["by_class"].items():
        lines.append(f"| {name} | " + " | ".join(_number(columns[column][metric]) for column in COLUMNS) + " |")

    return lines


def _verdicts(result: dict) -> tuple[str, str]:
    share = result["intermittent_beat"] / result["intermittent"] if result["intermittent"] else math.nan

    if math.isnan(share):
        sc008 = "**SC-008: not measurable.** This data has no intermittent products with demand in the scoring windows."
    else:
        outcome = "MET" if share >= 0.6 else "NOT MET"
        sc008 = (
            f"**SC-008: {outcome}.** The chosen method beat the 4-week moving average on WAPE for "
            f"{result['intermittent_beat']} of {result['intermittent']} intermittent or lumpy products "
            f"({share:.0%}, target 60%)."
        )

        if result["intermittent"] < SMALL_SAMPLE:
            sc008 += f" This rests on only {result['intermittent']} products, so it is too few to trust either way."

    chosen, baseline = result["simulation"]["Chosen"], result["simulation"][BASELINE]
    fewer = chosen["stockout_days"] < baseline["stockout_days"]
    leaner = chosen["average_stock"] <= baseline["average_stock"]
    sc009 = (
        f"**SC-009: {'MET' if fewer and leaner else 'NOT MET'}.** Chosen methods had {chosen['stockout_days']} stockout days "
        f"against {baseline['stockout_days']} for the baseline, at an average stock of {chosen['average_stock']:.1f} "
        f"against {baseline['average_stock']:.1f} units per product."
    )

    return sc008, sc009


def render_report(results: dict[str, dict], *, m5_error: str | None) -> str:
    lines = [
        "# Forecast Evaluation",
        "",
        "Generated by `forecast/evaluation/run.py`. Do not edit by hand.",
        "",
        "## Method",
        "",
        f"- Rolling-origin, expanding-window backtests at weekly grain, horizons 1 to {HORIZON}, up to {MAX_WINDOWS} origins per product.",
        "- Each product's method is picked by WAPE on the earlier half of its origins, then everything is scored on the later half. "
        "This keeps selection out of the score.",
        f"- Products with under {MIN_WEEKS} weeks of history are left out.",
        "- Lower WAPE and MASE are better. Bias near 0 is best, and a positive bias means over-forecasting.",
        "- The stock simulation uses the app's reorder rules with a 7-day lead time, a 7-day review period and a 90% service level. "
        "Unmet demand is lost.",
        "",
    ]

    for label, result in results.items():
        sc008, sc009 = _verdicts(result)
        synthetic = "synthetic" in label.lower()

        lines += [f"## {label}", ""]

        if synthetic:
            lines += [
                "This is synthetic data from `DemoSeeder`, not real shop data. It shows that the pipeline works, "
                "not how the methods perform in a real shop.",
                "",
            ]

        lines += [
            f"{result['products']} products evaluated, {result['excluded']} left out for short history. "
            f"{result['selection_windows']} selection and {result['scoring_windows']} scoring origins.",
            "",
            "Demand classes: " + ", ".join(f"{name} {count}" for name, count in result["class_counts"].items()) + ".",
            "",
            "Methods chosen: " + ", ".join(f"{name} {count}" for name, count in result["methods"].items()) + ".",
            "",
            sc008,
            "",
            sc009,
            "",
            "### WAPE by demand class",
            "",
            *_table(result, "wape"),
            "",
            "### MASE by demand class",
            "",
            *_table(result, "mase"),
            "",
            "### Bias by demand class",
            "",
            *_table(result, "bias"),
            "",
            "### Stock simulation",
            "",
            "| Method | Stockout days | Average stock per product |",
            "|---|---|---|",
            *[
                f"| {column} | {result['simulation'][column]['stockout_days']} | {result['simulation'][column]['average_stock']:.1f} |"
                for column in COLUMNS
            ],
            "",
            "### Worst products",
            "",
            "| Product | Class | Method | Mean units per week | WAPE | MA4 WAPE |",
            "|---|---|---|---|---|---|",
            *[
                f"| {item['name']} | {item['class']} | {item['method']} | {item['units']:.1f} | "
                f"{_number(item['wape'])} | {_number(item['baseline_wape'])} |"
                for item in result["worst"]
            ],
            "",
        ]

    if m5_error:
        lines += ["## M5", "", f"M5 was not evaluated: {m5_error}", ""]

    return "\n".join(lines)


def load_synthetic(path: Path) -> dict[int, dict]:
    series: dict[int, dict] = {}

    with path.open(newline="", encoding="utf-8") as file:
        for row in csv.DictReader(file):
            item = series.setdefault(int(row["product_id"]), {"name": row["product_name"], "sales": []})
            item["sales"].append(int(row["units"]))

    return series


def load_m5(directory: str) -> dict[int, dict]:
    """
    A fixed sample of one store's food items, summed from days into 7-day weeks.

    Reads the raw file: M5.load expands all 30,490 series to long format and runs out of memory.
    """
    from datasetsforecast.m5 import M5

    M5.download(directory)

    wide = pd.read_csv(f"{directory}/m5/datasets/sales_train_evaluation.csv")
    subset = wide[(wide["store_id"] == M5_STORE) & (wide["dept_id"] == M5_DEPARTMENT)].sort_values("item_id")
    rows = np.random.default_rng(M5_SEED).choice(len(subset), size=min(M5_SERIES, len(subset)), replace=False)
    day_columns = [column for column in subset.columns if column.startswith("d_")]

    series = {}

    for key, row in enumerate(sorted(rows), start=1):
        days = subset.iloc[row][day_columns].to_numpy(dtype=int)
        days = days[np.argmax(days > 0):]
        full = len(days) // 7 * 7
        series[key] = {"name": subset.iloc[row]["item_id"], "sales": days[:full].reshape(-1, 7).sum(axis=1).tolist()}

    return series


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--synthetic", type=Path, default=Path("/docs/data/synthetic_weekly.csv"))
    parser.add_argument("--m5-directory", default="/tmp/m5")
    arguments = parser.parse_args()

    results = {"Synthetic warung data": evaluate(load_synthetic(arguments.synthetic))}
    m5_error = None

    try:
        results["M5 subset (real Walmart sales)"] = evaluate(load_m5(arguments.m5_directory))
    except Exception as exception:  # noqa: BLE001 - the report must still say why M5 is missing.
        m5_error = f"{type(exception).__name__}: {exception}"

    arguments.output.write_text(render_report(results, m5_error=m5_error), encoding="utf-8")


if __name__ == "__main__":
    main()
