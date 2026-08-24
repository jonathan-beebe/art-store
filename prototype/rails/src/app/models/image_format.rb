# The image format the bytes themselves declare, read from their leading
# magic numbers. A browser-supplied filename or `Content-Type` header names
# nothing here — only the bytes decide. SVG carries no magic bytes of its
# own (it is markup, not a binary format with a signature), so it sniffs as
# nothing, the same as a PDF, a zip, or an executable.
module ImageFormat
  FORMATS = %i[png jpeg gif webp].freeze

  # Enough leading bytes to match any signature below, WebP's included: the
  # RIFF header plus the four-byte "WEBP" tag at offset 8.
  SNIFF_BYTES = 16

  PNG_SIGNATURE = [ 0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a ].freeze
  JPEG_SIGNATURE = [ 0xff, 0xd8, 0xff ].freeze
  GIF87A_SIGNATURE = "GIF87a".bytes.freeze
  GIF89A_SIGNATURE = "GIF89a".bytes.freeze
  RIFF_SIGNATURE = "RIFF".bytes.freeze
  WEBP_SIGNATURE = "WEBP".bytes.freeze
  WEBP_FORMAT_OFFSET = 8

  module_function

  # The format the leading bytes declare, or nil for anything else.
  def sniff(bytes)
    leading = bytes.to_s.b.bytes

    return :png if starts_with?(leading, PNG_SIGNATURE)
    return :jpeg if starts_with?(leading, JPEG_SIGNATURE)
    return :gif if starts_with?(leading, GIF87A_SIGNATURE) || starts_with?(leading, GIF89A_SIGNATURE)
    return :webp if webp?(leading)

    nil
  end

  def webp?(leading)
    starts_with?(leading, RIFF_SIGNATURE) && starts_with?(leading, WEBP_SIGNATURE, WEBP_FORMAT_OFFSET)
  end

  def starts_with?(leading, signature, offset = 0)
    return false if leading.length < offset + signature.length

    signature.each_with_index.all? { |byte, index| leading[offset + index] == byte }
  end
end
