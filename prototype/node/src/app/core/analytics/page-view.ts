type PageViewResponse = { method: string; statusCode: number; contentType: string | null }

export function isCountablePageView(response: PageViewResponse): boolean {
  const isGet = response.method.toUpperCase() === 'GET'
  const isSuccessStatus = response.statusCode >= 200 && response.statusCode < 300
  const isHtml = response.contentType !== null && response.contentType.toLowerCase().includes('text/html')
  return isGet && isSuccessStatus && isHtml
}

export function pageViewDay(now: Date): string {
  return now.toISOString().slice(0, 10)
}
