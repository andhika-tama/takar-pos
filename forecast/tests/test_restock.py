import pytest

from app.restock import suggest


def policy(**overrides):
    return {
        "rate": 14.0,
        "deviation": 4.0,
        "lead_time_days": 7,
        "review_period_days": 7,
        "service_level": 90,
        "on_hand": 8,
        "on_order": 0,
        "min_order_qty": 1,
        "pack_size": 6,
    } | overrides


def test_the_worked_example_from_user_story_8():
    result = suggest(**policy())

    assert result["safety_stock"] == pytest.approx(5.12)
    assert result["reorder_point"] == pytest.approx(19.12)
    assert result["target_level"] == pytest.approx(33.12)
    assert result["suggested_quantity"] == 30


def test_a_95_percent_service_level_uses_z_of_1_65():
    assert suggest(**policy(service_level=95))["safety_stock"] == pytest.approx(6.6)


def test_lead_time_in_the_safety_stock_is_in_weeks():
    assert suggest(**policy(lead_time_days=28))["safety_stock"] == pytest.approx(1.28 * 4 * 2)


def test_no_order_above_the_reorder_point():
    assert suggest(**policy(on_hand=20))["suggested_quantity"] == 0


def test_an_order_is_due_exactly_at_the_reorder_point():
    result = suggest(**policy(deviation=0, on_hand=14))

    assert result["reorder_point"] == pytest.approx(14)
    assert result["suggested_quantity"] == 18


def test_on_order_counts_towards_stock():
    assert suggest(**policy(on_hand=8, on_order=12))["suggested_quantity"] == 0


@pytest.mark.parametrize(
    ("minimum", "pack", "expected"),
    [
        (1, 1, 16),  # raw 15.12 rounds up to whole units
        (20, 1, 20),  # the minimum wins
        (20, 6, 24),  # a minimum that is not a pack multiple still fits both rules
        (1, 4, 16),
    ],
)
def test_quantity_is_ceil_of_max_raw_and_minimum_to_the_pack(minimum, pack, expected):
    # On hand 18 is below the reorder point of 19.12, and target 33.12 leaves raw 15.12.
    result = suggest(**policy(on_hand=18, min_order_qty=minimum, pack_size=pack))

    assert result["suggested_quantity"] == expected


def test_a_pack_size_of_zero_is_treated_as_one():
    assert suggest(**policy(pack_size=0))["suggested_quantity"] == 26


def test_slow_movers_have_safety_stock_capped_at_twice_lead_time_demand():
    result = suggest(**policy(rate=0.5, deviation=3.0))

    assert result["safety_stock"] == pytest.approx(2 * 0.5)


def test_the_cap_does_not_touch_products_selling_one_a_week_or_more():
    assert suggest(**policy(rate=1.0, deviation=3.0))["safety_stock"] == pytest.approx(1.28 * 3)


def test_zero_demand_suggests_nothing():
    result = suggest(**policy(rate=0.0, deviation=0.0, on_hand=0))

    assert result["suggested_quantity"] == 0
