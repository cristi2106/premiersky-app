{{-- Shared company logo <img> for every generated PDF (Contract, Quotation,
     repeating terms header, …). Expects the including document to define
     the CSS that sizes it — same pattern as pdf.partials.footer. Only ever
     set width (or max-width) on the descendant `img` selector and leave
     height unset/auto: the source file (resources/images/logo.png) is
     cropped tight to the artwork, so its intrinsic ratio is already
     correct and an unset height keeps it that way automatically. Setting
     both width and height independently is what previously stretched the
     logo out of proportion. --}}
@if ($logoData)
    <img src="{{ $logoData }}" alt="Company logo">
@endif
