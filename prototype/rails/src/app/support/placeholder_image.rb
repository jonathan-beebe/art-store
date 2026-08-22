require "zlib"
require "base64"
require "erb"

# Stand-in artwork for listings without an uploaded image. The palette and
# composition derive from the title so the same listing always renders the
# same picture and different listings look different.
module PlaceholderImage
  module_function

  SIZE = 800

  def svg(title)
    seed = Zlib.crc32(title)
    hue = seed % 360
    second_hue = (hue + 140 + ((seed >> 8) % 80)) % 360
    label = ERB::Util.html_escape(title.to_s[0, 40])

    <<~SVG
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 #{SIZE} #{SIZE}" width="#{SIZE}" height="#{SIZE}" role="img" aria-label="#{label}">
      <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="hsl(#{hue} 60% 88%)"/><stop offset="1" stop-color="hsl(#{second_hue} 55% 80%)"/></linearGradient></defs>
      <rect width="#{SIZE}" height="#{SIZE}" fill="url(#g)"/>
      #{shapes(seed, hue, second_hue)}
      <text x="40" y="760" font-family="ui-sans-serif, system-ui, sans-serif" font-size="28" fill="hsl(#{hue} 40% 25%)">#{label}</text>
      </svg>
    SVG
  end

  def data_uri(title)
    "data:image/svg+xml;base64,#{Base64.strict_encode64(svg(title))}"
  end

  def shapes(seed, hue, second_hue)
    count = 3 + (seed % 4)
    (0...count).map do |index|
      step = (seed >> (index * 3)) & 0xFFFF
      x = 100 + ((step * 7) % (SIZE - 200))
      y = 100 + ((step * 13) % (SIZE - 300))
      size = 80 + ((step * 3) % 220)
      fill_hue = index.even? ? hue : second_hue
      if (index % 3).zero?
        %(<circle cx="#{x}" cy="#{y}" r="#{size}" fill="hsl(#{fill_hue} 55% 55% / 0.45)"/>)
      else
        %(<rect x="#{x}" y="#{y}" width="#{size}" height="#{size}" rx="24" fill="hsl(#{fill_hue} 50% 50% / 0.4)"/>)
      end
    end.join
  end
end
