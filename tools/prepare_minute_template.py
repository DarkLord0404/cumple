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
                agenda_tables = root.xpath(
                    ".//w:tbl[contains(string(.), 'AGENDA / TEMAS A TRATAR')]",
                    namespaces=namespace,
                )
                if agenda_tables:
                    agenda_rows = agenda_tables[0].xpath("./w:tr[position() > 1]", namespaces=namespace)
                    for index, row in enumerate(agenda_rows[:4], start=1):
                        cells = row.xpath("./w:tc", namespaces=namespace)
                        if len(cells) < 3:
                            continue
                        for cell, marker in (
                            (cells[-2], f"{{{{agendaNumero{index}}}}}"),
                            (cells[-1], f"{{{{agenda{index}}}}}"),
                        ):
                            texts = cell.xpath(".//w:t", namespaces=namespace)
                            if texts:
                                texts[0].text = marker
                                for extra in texts[1:]:
                                    extra.text = ""
                            else:
                                paragraphs = cell.xpath("./w:p", namespaces=namespace)
                                paragraph = paragraphs[0] if paragraphs else etree.SubElement(cell, f"{{{namespace['w']}}}p")
                                run = etree.SubElement(paragraph, f"{{{namespace['w']}}}r")
                                etree.SubElement(run, f"{{{namespace['w']}}}t").text = marker

                for row in list(root.xpath(".//w:tr", namespaces=namespace)):
                    text = "".join(row.xpath(".//w:t/text()", namespaces=namespace))
                    if any(marker in text for marker in ("{{participante2}}", "{{participante3}}", "{{compromiso2}}")):
                        row.getparent().remove(row)
                    elif "{{compromiso1}}" in text:
                        first_cell_text = row.xpath("./w:tc[1]//w:t", namespaces=namespace)
                        if first_cell_text:
                            first_cell_text[0].text = "{{compromisoNumero}}"

                for cell in root.xpath(".//w:tbl//w:tc", namespaces=namespace):
                    properties = cell.find("w:tcPr", namespaces=namespace)
                    if properties is None:
                        properties = etree.Element(f"{{{namespace['w']}}}tcPr")
                        cell.insert(0, properties)
                    alignment = properties.find("w:vAlign", namespaces=namespace)
                    if alignment is None:
                        alignment = etree.SubElement(properties, f"{{{namespace['w']}}}vAlign")
                    alignment.set(f"{{{namespace['w']}}}val", "center")

                    for paragraph in cell.xpath("./w:p", namespaces=namespace):
                        paragraph_properties = paragraph.find("w:pPr", namespaces=namespace)
                        if paragraph_properties is None:
                            paragraph_properties = etree.Element(f"{{{namespace['w']}}}pPr")
                            paragraph.insert(0, paragraph_properties)
                        spacing = paragraph_properties.find("w:spacing", namespaces=namespace)
                        if spacing is None:
                            spacing = etree.SubElement(paragraph_properties, f"{{{namespace['w']}}}spacing")
                        spacing.set(f"{{{namespace['w']}}}before", "0")
                        spacing.set(f"{{{namespace['w']}}}after", "0")
                data = etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone="yes")
            archive_out.writestr(item, data)
    shutil.copyfile(output, source)
