export const LISTING_EVENT_TYPES = ['view', 'favorite', 'unfavorite', 'cart_add'] as const
export type ListingEventType = (typeof LISTING_EVENT_TYPES)[number]
