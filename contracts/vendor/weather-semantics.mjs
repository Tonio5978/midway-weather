// Contract conformance only: no collection, classification, or producer implementation.
// JSON Schema validates structure first; this checks relationships between values.
export function weatherSemanticErrors(payload, httpStatus = 200) {
  const errors = [];
  const check = (condition, message) => { if (!condition) errors.push(message); };
  const time = (value) => Date.parse(value);
  const generated = time(payload.meta.generated_at);

  if (payload.error) {
    check(httpStatus >= 400 && httpStatus <= 599, "Errors require a 4xx/5xx status");
    if (payload.error.code === "weather_data_unavailable") {
      check(httpStatus === 503, "weather_data_unavailable requires HTTP 503");
    }
    if (payload.error.code === "internal_error") {
      check(httpStatus === 500, "internal_error requires HTTP 500");
    }
    return errors;
  }

  check(httpStatus === 200, "Usable projections require HTTP 200");
  const { observation, observed_today: observed, forecast_today: forecast, interpretation } = payload.data;
  let localParts;
  try {
    const formatter = new Intl.DateTimeFormat("en-CA", {
      timeZone: payload.data.timezone, year: "numeric", month: "2-digit", day: "2-digit",
      hour: "2-digit", minute: "2-digit", second: "2-digit", hourCycle: "h23",
    });
    // Reject numeric offsets even on runtimes that support them as timeZone options.
    check(!/^[+-]/.test(payload.data.timezone), "timezone must be an IANA name");
    localParts = (instant) => Object.fromEntries(formatter.formatToParts(instant).map((p) => [p.type, p.value]));
  } catch {
    errors.push("timezone must be a supported IANA name");
    return errors;
  }

  const localDate = (parts) => `${parts.year}-${parts.month}-${parts.day}`;
  function period(value, label) {
    const start = time(value.starts_at);
    const end = time(value.ends_at);
    check(start <= generated && generated < end, `${label} must cover the response's local day`);
    const a = localParts(start);
    const b = localParts(end);
    check([a, b].every((p) => p.hour === "00" && p.minute === "00" && p.second === "00")
      && start % 1000 === 0 && end % 1000 === 0, `${label} boundaries must be exact local midnights`);
    const next = new Date(`${localDate(a)}T00:00:00Z`);
    next.setUTCDate(next.getUTCDate() + 1);
    check(localDate(b) === next.toISOString().slice(0, 10), `${label} must span one local calendar day, including DST`);
    check(localDate(a) === localDate(localParts(generated)), `${label} cannot relabel a previous day as today`);
  }

  function freshness(value, sourceTime, label) {
    const fresh = time(value.fresh_until);
    const stale = time(value.stale_after ?? value.fresh_until);
    check(time(sourceTime) <= generated, `${label} source time cannot be in the future`);
    check(fresh >= time(sourceTime) && stale >= fresh, `${label} freshness deadlines must follow source time in order`);
    const expected = generated <= fresh ? "fresh" : generated <= stale ? "delayed" : "stale";
    check(value.state === expected, `${label} freshness state must agree with deadlines at meta.generated_at`);
  }

  freshness(observation.freshness, observation.observed_at, "observation");
  if (observation.retrieved_at !== null) {
    check(time(observation.retrieved_at) >= time(observation.observed_at)
      && time(observation.retrieved_at) <= generated, "Observation retrieval must follow observation and precede generation");
  }
  if (observed) {
    period(observed.period, "observed_today");
    freshness(observed.freshness, observed.as_of_at, "observed_today");
    check(time(observed.as_of_at) >= time(observed.period.starts_at)
      && time(observed.as_of_at) < time(observed.period.ends_at), "Observed extrema must use observations from their day");
    check(time(observed.as_of_at) <= time(observation.observed_at), "Observed extrema cannot postdate the selected latest usable observation");
    check(observed.temperature_low_f <= observed.temperature_high_f, "Observed low cannot exceed high");
  }
  if (forecast) {
    period(forecast.period, "forecast_today");
    freshness(forecast.freshness, forecast.retrieved_at, "forecast_today");
    if (forecast.temperature_high_f !== null && forecast.temperature_low_f !== null) {
      check(forecast.temperature_low_f <= forecast.temperature_high_f, "Forecast low cannot exceed high");
    }
  }
  if (!forecast || observation.last_known_good || forecast.last_known_good) {
    check(payload.data.availability === "degraded", "Missing forecast or retained failure fallback requires degraded availability");
  }
  if (interpretation) {
    check(interpretation.based_on.includes("forecast_today") === Boolean(forecast), "Interpretation must declare all considered components; never reference a missing forecast");
    const inputs = [observation.freshness];
    if (forecast) inputs.push(forecast.freshness);
    const fresh = Math.min(...inputs.map((f) => time(f.fresh_until)));
    const stale = Math.min(...inputs.map((f) => time(f.stale_after ?? f.fresh_until)));
    check(time(interpretation.freshness.fresh_until) === fresh
      && time(interpretation.freshness.stale_after) === stale, "Interpretation must inherit the earliest input freshness deadlines");
    freshness(interpretation.freshness, new Date(Math.min(time(observation.observed_at), forecast ? time(forecast.retrieved_at) : Infinity)).toISOString(), "interpretation");
  }
  return errors;
}
