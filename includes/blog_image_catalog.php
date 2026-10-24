<?php
declare(strict_types=1);

/** Photos supplied for the blog pages; other public sections use their own assets. */
function supplied_blog_image_catalog(): array
{
    $photos = [
        'sunset' => ['Lawn at sunset', 'lawn-sunset', 'Green event lawn and venue building beneath a colourful sunset'],
        'social' => ['Evening lawn gathering', 'evening-gathering', 'Guests gathering around illuminated tables on the lawn at night'],
        'cocktail' => ['Evening table layout', 'evening-table-layout', 'Aerial view of cocktail tables, warm string lights and lawn seating'],
        'mehendi' => ['Mehendi celebration', 'mehendi-celebration', 'Bride having mehendi applied with friends beneath colourful floral decorations'],
        'aisle' => ['Wedding ceremony aisle', 'wedding-aisle', 'Wedding aisle leading to a floral mandap at sunset'],
        'sangeet' => ['Sangeet celebration', 'sangeet-celebration', 'Couple dancing beneath flowers and warm lights at a sangeet celebration'],
        'mandap' => ['Wedding lawn and mandap', 'wedding-mandap', 'Outdoor wedding seating and floral mandap against the sunset sky'],
        'reception' => ['Garden reception styling', 'garden-reception', 'Garden reception with floral arrangements, round tables and warm lighting'],
        'entrance' => ['Garden entrance', 'garden-entrance', 'Flower-covered entrance arch opening onto the green lawn'],
        'birthday' => ['First birthday celebration', 'first-birthday', 'Pink first birthday decorations and round tables arranged on the lawn'],
        'dinner' => ['Dinner under the lights', 'evening-dinner', 'Outdoor dinner tables beneath warm festoon lights at night'],
        'aerial' => ['Lawn from above', 'daylight-lawn-aerial', 'Daylight aerial view of the open lawn, venue building and entrance drive'],
        'haldi' => ['Haldi ceremony', 'haldi-ceremony', 'Couple celebrating haldi with friends beneath yellow marigold decorations'],
        'haldi-joy' => ['Haldi with family and friends', 'haldi-celebration', 'Family and friends showering a couple with turmeric during a haldi celebration'],
        'birthday-aerial' => ['Birthday lawn layout', 'birthday-lawn-aerial', 'Aerial view of pink birthday seating, balloon decorations and string lights'],
    ];
    $groups = [
        'wedding-venue-visakhapatnam' => ['mandap', 'aisle', 'reception'],
        'event-lawns-visakhapatnam' => ['sunset', 'aerial', 'cocktail'],
        'function-hall-madhurawada' => ['sunset', 'aerial', 'reception'],
        'birthday-party-venue-visakhapatnam' => ['birthday', 'birthday-aerial', 'entrance'],
        'engagement-venue-visakhapatnam' => ['reception', 'mandap', 'dinner'],
        'corporate-event-venue-visakhapatnam' => ['cocktail', 'social', 'aerial'],
        'reception-venue-visakhapatnam' => ['reception', 'social', 'dinner'],
        'wedding-lawns-madhurawada' => ['mandap', 'aisle', 'sunset'],
        'event-venue-kommadi' => ['sunset', 'entrance', 'aerial'],
        'venues-near-madhurawada' => ['aerial', 'entrance', 'sunset'],
        'venues-near-rushikonda' => ['sunset', 'aerial', 'dinner'],
        'outdoor-event-venue-visakhapatnam' => ['reception', 'cocktail', 'sunset'],
        'private-event-venue-visakhapatnam' => ['cocktail', 'dinner', 'aerial'],
        'party-venue-madhurawada' => ['social', 'birthday', 'cocktail'],
        'haldi-venue-visakhapatnam' => ['haldi', 'haldi-joy', 'sunset'],
        'mehendi-venue-visakhapatnam' => ['mehendi', 'entrance', 'sunset'],
        'haldi-mehendi-venue-visakhapatnam' => ['haldi', 'mehendi', 'haldi-joy'],
        'sangeet-venue-visakhapatnam' => ['sangeet', 'social', 'dinner'],
        'anniversary-venue-visakhapatnam' => ['dinner', 'reception', 'cocktail'],
        'family-celebration-venue-visakhapatnam' => ['birthday', 'social', 'dinner'],
        '300-guest-event-venue' => ['aerial', 'mandap', 'reception'],
    ];
    $catalog = [];
    foreach ($groups as $slug => $keys) {
        foreach ($keys as $index => $key) {
            [$title, $filename, $alt] = $photos[$key];
            $catalog[$slug][] = [$title, 'assets/blogs/' . $filename . '.jpg', $alt, $index + 1];
        }
    }
    return $catalog;
}
