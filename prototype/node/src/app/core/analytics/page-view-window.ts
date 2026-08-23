/** A run of calendar days, `YYYY-MM-DD`, inclusive at both ends. */
export type DayRange = { firstDay: string; lastDay: string }

const DAYS_IN_A_WEEK = 7

/**
 * What "this week" means where traffic is concerned: the seven days ending
 * today. A Monday-to-Sunday week would read as almost nothing every Monday,
 * and the number is there to be compared with the day before it.
 */
export function pageViewWeek(today: string): DayRange {
  const lastDay = new Date(`${today}T00:00:00.000Z`)
  const firstDay = new Date(lastDay.getTime())
  firstDay.setUTCDate(firstDay.getUTCDate() - (DAYS_IN_A_WEEK - 1))

  return { firstDay: firstDay.toISOString().slice(0, 10), lastDay: today }
}
