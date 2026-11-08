import pytest

from app import forecasting
from app.forecasting import classify, forecast_items, n_windows, pick_method


def item(product_id, weekly_sales, category_id=1):
    return {"product_id": product_id, "category_id": category_id, "weekly_sales": weekly_sales}


def one(weekly_sales):
    return forecast_items([item(1, weekly_sales)])[1]


@pytest.mark.parametrize(("weeks", "expected"), [(8, 1), (9, 2), (18, 11), (19, 12), (104, 12)])
def test_backtest_windows(weeks, expected):
    assert n_windows(weeks) == expected


def test_the_8_week_minimum_produces_a_real_forecast():
    result = one([4, 0, 7, 3, 5, 0, 2, 6])

    assert result["status"] == "ok"
    assert result["method"] in {"MA4", "SES", "Croston", "SBA", "TSB"}
    assert len(result["forecast"]) == 4
    assert result["baseline"] == [3.25] * 4
    assert result["demand_deviation"] >= 0


def scores(**values):
    return {method: {"wape": wape, "all_zero": False} for method, wape in values.items()}


def test_the_lowest_wape_wins():
    assert pick_method(scores(MA4=0.5, SES=0.4, Croston=0.3, SBA=0.6, TSB=0.45)) == "Croston"


def test_a_tie_goes_to_the_moving_average():
    assert pick_method(scores(MA4=0.3, SES=0.3, Croston=0.3, SBA=0.3, TSB=0.3)) == "MA4"


def test_undefined_wape_chooses_the_moving_average():
    undefined = float("nan")

    assert pick_method(scores(MA4=undefined, SES=undefined, Croston=undefined, SBA=undefined, TSB=undefined)) == "MA4"


def test_a_method_forecasting_all_zero_is_ineligible():
    candidates = scores(MA4=0.5, SES=0.6, Croston=0.7, SBA=0.8, TSB=0.1)
    candidates["TSB"]["all_zero"] = True

    assert pick_method(candidates) == "MA4"


def test_the_moving_average_stays_when_every_method_forecasts_zero():
    candidates = scores(MA4=0.1, SES=0.2, Croston=0.3, SBA=0.4, TSB=0.5)

    for candidate in candidates.values():
        candidate["all_zero"] = True

    assert pick_method(candidates) == "MA4"


@pytest.mark.parametrize(
    ("series", "expected"),
    [
        ([10, 11, 9, 10, 10, 11, 9, 10], "smooth"),
        ([1, 30, 2, 40, 1, 25, 3, 50], "erratic"),
        ([5, 0, 0, 5, 0, 0, 5, 0], "intermittent"),
        ([1, 0, 0, 40, 0, 0, 2, 0], "lumpy"),
    ],
)
def test_demand_classes(series, expected):
    assert classify(series) == expected


def test_all_zero_history_keeps_the_moving_average_and_forecasts_zero():
    result = one([0] * 10)

    assert result["method"] == "MA4"
    assert result["forecast"] == [0.0] * 4


def test_4_to_7_weeks_use_the_mean_with_the_sample_deviation():
    result = one([2, 4, 6, 8])

    assert result["status"] == "insufficient_data"
    assert result["method"] == "AVG"
    assert result["demand_class"] is None
    assert result["weekly_rate"] == pytest.approx(5)
    assert result["forecast"] == [5.0] * 4
    assert result["demand_deviation"] == pytest.approx(2.5819889)


def test_under_4_weeks_use_the_category_mean_with_no_safety_deviation():
    results = forecast_items(
        [
            item(1, [2, 2, 2, 2]),
            item(2, [4, 4, 4, 4, 4, 4, 4, 4, 4]),
            item(3, [9, 9]),
            item(4, [100, 100, 100, 100], category_id=2),
        ]
    )

    assert results[3]["status"] == "insufficient_data"
    assert results[3]["method"] == "CATEGORY"
    assert results[3]["weekly_rate"] == pytest.approx(3)
    assert results[3]["demand_deviation"] == 0


def test_under_4_weeks_with_no_category_data_gives_no_rate():
    result = one([9, 9])

    assert result["status"] == "insufficient_data"
    assert result["weekly_rate"] is None
    assert result["forecast"] == []


def test_an_unsold_product_is_handled():
    result = one([])

    assert result["status"] == "insufficient_data"
    assert result["baseline"] == []


def test_short_history_still_shows_a_baseline():
    assert one([3, 5])["baseline"] == [4.0] * 4


def test_series_of_different_lengths_are_forecast_together():
    results = forecast_items([item(1, [3] * 8), item(2, [5] * 30), item(3, [1, 0] * 10)])

    assert {result["status"] for result in results.values()} == {"ok"}


def test_the_candidate_methods_are_those_the_spec_names():
    assert [model.alias for model in forecasting.models()] == ["MA4", "SES", "Croston", "SBA", "TSB"]
