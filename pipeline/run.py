"""Chạy toàn bộ pipeline: python run.py [--skip-download]. Xong thì nạp vào web:
    cd ../web && php artisan webgis:import ../pipeline/output
"""
import sys
import warnings

warnings.filterwarnings("ignore")

import config as C
import s1_composite, s2_water, s3_shoreline, s4_transects, s5_stats, s6_reference

if __name__ == "__main__":
    if "--skip-download" not in sys.argv:
        s1_composite.main(C.YEARS)
    s2_water.main(C.YEARS)
    s3_shoreline.main(C.YEARS)
    s4_transects.main()
    s5_stats.main()
    s6_reference.main()
    print(f"\nXong. Nạp vào web: cd ../web && php artisan webgis:import {C.OUTPUT}")
