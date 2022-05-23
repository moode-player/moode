#!/bin/python3
#
# moOde audio player (C) 2014 Tim Curtis
#
# (C) 2021 @bitlab (@bitkeeper Git)
# http://moodeaudio.org
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License version 2 as
# published by the Free Software Foundation.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <https://www.gnu.org/licenses/>.
#
# Show Pi revision code information
#
# Revision code information taken from:
# https://www.raspberrypi.org/documentation/hardware/raspberrypi/revision-codes/README.md
#
# Generates an image of camilladsp pipeline with camilladsp_plot library
#
import sys
import os
import yaml
from camilladsp_plot.plot_pipeline import plot_pipeline

def main():
    #TODO: not a very safe construction
    pipelinefilename = sys.argv[1]
    pipelinefile = open(pipelinefilename)
    conf = yaml.safe_load(pipelinefile)
    img = plot_pipeline(conf, True)
    with os.fdopen(sys.stdout.fileno(), "wb", closefd=False) as stdout:
        stdout.writelines(img.readlines() )
        stdout.flush()

if __name__ == "__main__":
    main()