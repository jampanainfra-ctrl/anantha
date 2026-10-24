# Google Maps reviews

The homepage, gallery, and public reviews page show Google Maps reviews only. Website feedback remains in Admin > Reviews for internal management; it is not substituted for Google content. Visiting the reviews page no longer inserts sample reviews.

## Connect the listing

1. In Google Cloud Console, create or select a project and link billing.
2. Enable **Places API (New)**, then create an API key under **APIs & Services > Credentials**.
3. Restrict the key to **Places API (New)** and, for this server-side connection, the hosting server's outbound IP addresses. Ask the host for those IP addresses. A browser website/referrer restriction is unsuitable for PHP requests.
4. Obtain the Place ID for Anantha Event Lawns using Google's Place ID finder. Check the business name and address before copying it.
5. Open `/ceoadmin/reviews/#google-connection`, paste the API key and Place ID, then select **Save Google connection** and **Test connection**.
6. Check the homepage, `/galary/`, and `/review/`. The live deployment requires its own settings; private settings are not committed or packaged with source updates.

Google Cloud billing, quotas and usage charges apply. Set suitable quotas and budget alerts in the Google project. The Places API provides a limited selection of reviews, ordered by relevance. It does not return every review on the listing. Links on the website let visitors read the rest on Google Maps.

Saved settings are in `storage/google-reviews-settings.php`, protected by `storage/.htaccess` and excluded from Git. The key is never filled back into the admin page. Leave its field blank to preserve it. Alternatively, the host can set `GOOGLE_PLACES_API_KEY`, `GOOGLE_PLACE_ID`, and `GOOGLE_REVIEW_URL` environment variables; these override file settings. `ANANTHA_GOOGLE_SETTINGS_PATH` is a test-only private settings path override.

The direct Maps link uses the venue's existing listing link. Reviewer names link to their Google profiles when provided, and each card includes Google Maps attribution. Aggregate ratings and review counts come from Google, rather than being calculated from a few excerpts. No sample rating is shown while disconnected. Review text is escaped and Google content is not saved to the application's database or a disk cache.

If connection testing fails, check Places API access, linked billing, API key restrictions, the Place ID, PHP cURL, and the host's trusted CA certificates. HTTPS certificate verification remains enabled. Tests use fixture responses and temporary settings; they do not call a paid Google API.

Official setup: https://developers.google.com/maps/documentation/places/web-service/get-api-key

Place IDs: https://developers.google.com/maps/documentation/places/web-service/place-id

Place Details: https://developers.google.com/maps/documentation/places/web-service/place-details

Attribution and content rules: https://developers.google.com/maps/documentation/places/web-service/policies
