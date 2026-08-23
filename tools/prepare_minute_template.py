from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile
import shutil
import tempfile
from lxml import etree


source = Path(__file__).resolve().parents[1] / "resources" / "templates" / "plantilla_acta_institucional.docx"
namespace = {"w": "http://schemas.openxmlformats.org/wordprocessingml/2006/main"}

with tempfile.TemporaryDirectory() as temporary:
    output = Path(temporary) / source.name
    with ZipFile(source, "r") as archive_in, ZipFile(output, "w", ZIP_DEFLATED) as archive_out:
        for item in archive_in.infolist():
            data = archive_in.read(item.filename)
            if item.filename == "word/document.xml":
                root = etree.fromstring(data)
                for row in list(root.xpath(".//w:tr", namespaces=namespace)):
                    text = "".join(row.xpath(".//w:t/text()", namespaces=namespace))
                    if any(marker in text for marker in ("{{participante2}}", "{{participante3}}", "{{compromiso2}}")):
                        row.getparent().remove(row)
                    elif "{{compromiso1}}" in text:
                        first_cell_text = row.xpath("./w:tc[1]//w:t", namespaces=namespace)
                        if first_cell_text:
                            first_cell_text[0].text = "{{compromisoNumero}}"
                data = etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone="yes")
            archive_out.writestr(item, data)
    shutil.copyfile(output, source)
