import pytest

from evaluation.run import bias, evaluate, mase, render_report, wape
from evaluation.simulation import simulate


def test_wape_is_absolute_error_over_actual_volume():
    assert wape([10, 0, 10], [8, 1, 13]) == pytest.approx(6 / 20)


def test_wape_is_undefined_with_no_demand():
    assert wape([0, 0], [1, 1]) != wape([0, 0], [1, 1])


def test_mase_scales_by_the_naive_in_sample_error():
    # Naive differences of the history are 2, 2 and 2.
    assert mase([10, 12], [11, 11], history=[2, 4, 6, 8]) == pytest.approx(0.5)


def test_mase_is_undefined_on_a_flat_history():
    assert mase([1], [1], history=[3, 3, 3]) != mase([1], [1], history=[3, 3, 3])


def test_bias_is_signed_error_over_actual_volume():
    assert bias([10, 10], [12, 12]) == pytest.approx(0.2)
    assert bias([10, 10], [8, 8]) == pytest.approx(-0.2)


def test_a_week_of_demand_above_stock_counts_its_stockout_days():
    # 7 a week is 1 a day. 3 units last 3 days, leaving 4 days short with nothing arriving.
    result = simulate([7], rates=[0.0], deviations=[0.0], initial_stock=3)

    assert result["stockout_days"] == 4


def test_enough_stock_has_no_stockout_days():
    result = simulate([7, 7], rates=[0.0, 0.0], deviations=[0.0, 0.0], initial_stock=14)

    assert result["stockout_days"] == 0


def test_average_stock_is_the_mean_end_of_day_level():
    # Starting at 7 and selling 1 a day: 6, 5, 4, 3, 2, 1, 0.
    result = simulate([7], rates=[0.0], deviations=[0.0], initial_stock=7)

    assert result["average_stock"] == pytest.approx(3)


def test_orders_arrive_after_the_lead_time():
    # 2 a day from 7 units runs short on days 4 to 7. The order lands for week 2, which has no shortage.
    result = simulate(
        [14, 14],
        rates=[14.0, 14.0],
        deviations=[0.0, 0.0],
        initial_stock=7,
        lead_time_days=7,
    )

    assert result["stockout_days"] == 4


def weeks(pattern, count):
    return (pattern * count)[:count]


SERIES = {
    1: {"name": "Lancar", "sales": weeks([10, 12, 9, 11], 60)},
    2: {"name": "Jarang", "sales": weeks([0, 0, 5, 0, 0, 0, 4, 0], 60)},
    3: {"name": "Baru", "sales": weeks([3], 10)},
}


@pytest.fixture(scope="module")
def result():
    return evaluate(SERIES)


def test_every_method_and_the_chosen_one_are_scored(result):
    assert {"MA4", "SES", "Croston", "SBA", "TSB", "Chosen"} <= set(result["by_class"]["smooth"])


def test_products_without_enough_history_are_left_out_and_counted(result):
    assert result["excluded"] == 1
    assert result["products"] == 2


def test_methods_are_chosen_on_earlier_windows_than_they_are_scored_on(result):
    assert result["selection_windows"] > 0
    assert result["scoring_windows"] > 0


def test_the_report_has_every_required_section(result):
    report = render_report({"Synthetic": result}, m5_error=None)

    for heading in ("WAPE", "MASE", "Bias", "Stockout days", "Average stock", "Worst products", "SC-008", "SC-009", "synthetic"):
        assert heading in report


def test_the_report_never_mentions_the_banned_metric(result):
    assert "mape" not in render_report({"Synthetic": result}, m5_error=None).lower()


def test_a_verdict_on_few_products_is_flagged_as_unreliable(result):
    assert "too few to trust" in render_report({"Synthetic": result}, m5_error=None)


def test_the_report_says_plainly_when_m5_could_not_run(result):
    assert "M5 was not evaluated" in render_report({"Synthetic": result}, m5_error="no network")


def test_the_report_is_repeatable(result):
    assert render_report({"Synthetic": result}, m5_error=None) == render_report({"Synthetic": evaluate(SERIES)}, m5_error=None)
